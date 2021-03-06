<?php

namespace GitList\Git;

use Gitter\Client as BaseClient;

class Client extends BaseClient
{
    protected $defaultBranch;
    protected $hidden;

    public function __construct($options = null)
    {
        parent::__construct($options['path']);
        $this->setDefaultBranch($options['default_branch']);
        $this->setHidden($options['hidden']);
    }

    public function getRepositoryFromName($paths, $repo)
    {
        $repositories = $this->getRepositories($paths);
        $path = $repositories[$repo]['path'];

        return $this->getRepository($path);
    }

    /**
     * Searches for valid repositories on the specified path
     *
     * @param  array $paths Array of paths where repositories will be searched
     * @return array Found repositories, containing their name, path and description
     */
    public function getRepositories($paths)
    {
        $allRepositories = array();

        foreach ($paths as $path) {
            $repositories = $this->recurseDirectory($path);

            if (empty($repositories)) {
                throw new \RuntimeException('There are no GIT repositories in ' . $path);
            }

            $allRepositories = array_merge($allRepositories, $repositories);
        }

        $allRepositories = array_unique($allRepositories, SORT_REGULAR);
        asort($allRepositories);

        return $allRepositories;
    }

    private function recurseDirectory($path, $topLevel = true)
    {
        $dir = new \DirectoryIterator($path);

        $repositories = array();

        foreach ($dir as $file) {
            if ($file->isDot()) {
                continue;
            }

            if (strrpos($file->getFilename(), '.') === 0) {
                continue;
            }

            if (!$file->isReadable()) {
                continue;
            }

            if ($file->isDir()) {
                $isBare = file_exists($file->getPathname() . '/HEAD');
                $isRepository = file_exists($file->getPathname() . '/.git/HEAD');
                $isSubRepo = is_file($file->getPathname() . '/.git');

                if ($isRepository || $isBare || $isSubRepo) {
                    if (in_array($file->getPathname(), $this->getHidden())) {
                        continue;
                    }

                    if ($isSubRepo) {
                        $gitdir = file_get_contents($file->getPathname() . '/.git');
                        if ($gitdir === false || preg_match('/^gitdir: *(.+)$/', $gitdir, $matches) != 1) {
                            continue;
                        }
                        $repoPath = $file->getPathname() . '/' . $matches[1];
                    }
                    else {
                        $repoPath = $file->getPathname();
                    }

                    if ($isBare) {
                        $description = $repoPath . '/description';
                    } else {
                        $description = $repoPath . '/.git/description';
                    }

                    if (file_exists($description)) {
                        $description = file_get_contents($description);
                    } else {
                        $description = null;
                    }

                    if (!$topLevel) {
                        $repoName = $file->getPathInfo()->getFilename() . '/' . $file->getFilename();
                    } else {
                        $repoName = $file->getFilename();
                    }

                    $repositories[$repoName] = array(
                        'name' => $repoName,
                        'path' => $repoPath,
                        'description' => $description
                    );

                    $hasSubMods = is_file($file->getPathname() . '/.gitmodules');
                    if ($isBare || !$hasSubMods) {
                        continue;
                    }
                }

                $repositories = array_merge($repositories, $this->recurseDirectory($file->getPathname(), false));
            }
        }

        return $repositories;
    }

    /**
     * Set default branch as a string.
     *
     * @param string $branch Name of branch to use when repo's HEAD is detached.
     */
    protected function setDefaultBranch($branch)
    {
        $this->defaultBranch = $branch;

        return $this;
    }

    /**
     * Return name of default branch as a string.
     */
    public function getDefaultBranch()
    {
        return $this->defaultBranch;
    }

    /**
     * Get hidden repository list
     *
     * @return array List of repositories to hide
     */
    protected function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden repository list
     *
     * @param array $hidden List of repositories to hide
     */
    protected function setHidden($hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Overloads the parent::createRepository method for the correct Repository class instance
     * 
     * {@inheritdoc}
     */
    public function createRepository($path, $bare = null)
    {
        if (file_exists($path . '/.git/HEAD') && !file_exists($path . '/HEAD')) {
            throw new \RuntimeException('A GIT repository already exists at ' . $path);
        }

        $repository = new Repository($path, $this);

        return $repository->create($bare);
    }

    /**
     * Overloads the parent::getRepository method for the correct Repository class instance
     * 
     * {@inheritdoc}
     */
    public function getRepository($path)
    {
        if (!file_exists($path) || !file_exists($path . '/.git/HEAD') && !file_exists($path . '/HEAD')) {
            throw new \RuntimeException('There is no GIT repository at ' . $path);
        }

        return new Repository($path, $this);
    }
}

