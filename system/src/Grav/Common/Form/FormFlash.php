<?php

/**
 * @package    Grav\Framework\Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Form;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\User\User;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class FormFlash extends \Grav\Framework\Form\FormFlash
{
    /**
     * @param string $sessionId
     */
    public static function clearSession(string $sessionId): void
    {
        $folder = static::getSessionTmpDir($sessionId);
        if (is_dir($folder)) {
            Folder::delete($folder);
        }
    }

    /**
     * @param string $sessionId
     * @return string
     */
    public static function getSessionTmpDir(string $sessionId): string
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $locator->findResource("tmp://forms/{$sessionId}", true, true);
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user = null)
    {
        if ($user && $user->username) {
            $this->user = [
                'username' => $user->username,
                'email' => $user->email ?? ''
            ];
        } else {
            $this->user = null;
        }

        return $this;
    }

    /**
     * @return array
     * @deprecated 1.6 For backwards compatibility only, do not use.
     */
    public function getLegacyFiles(): array
    {
        $fields = [];
        foreach ($this->files as $field => $files) {
            if (strpos($field, '/')) {
                continue;
            }
            foreach ($files as $file) {
                if (\is_array($file)) {
                    $file['tmp_name'] = $this->getTmpDir() . '/' . $file['tmp_name'];
                    $fields[$field][$file['path'] ?? $file['name']] = $file;
                }
            }
        }

        return $fields;
    }

    /**
     * @param string $field
     * @param string $filename
     * @param array $upload
     * @return bool
     * @deprecated 1.6 For backwards compatibility only, do not use.
     */
    public function uploadFile(string $field, string $filename, array $upload): bool
    {
        $tmp_dir = $this->getTmpDir();

        Folder::create($tmp_dir);

        $tmp_file = $upload['file']['tmp_name'];
        $basename = basename($tmp_file);

        if (!move_uploaded_file($tmp_file, $tmp_dir . '/' . $basename)) {
            return false;
        }

        $upload['file']['tmp_name'] = $basename;

        if (!isset($this->files[$field])) {
            $this->files[$field] = [];
        }

        // Prepare object for later save
        $upload['file']['name'] = $filename;

        // Replace old file, including original
        $oldUpload = $this->files[$field][$filename] ?? null;
        if (isset($oldUpload['tmp_name'])) {
            $this->removeTmpFile($oldUpload['tmp_name']);
        }

        $originalUpload = $this->files[$field . '/original'][$filename] ?? null;
        if (isset($originalUpload['tmp_name'])) {
            $this->removeTmpFile($originalUpload['tmp_name']);
            unset($this->files[$field . '/original'][$filename]);
        }

        // Prepare data to be saved later
        $this->files[$field][$filename] = $upload['file'];

        return true;
    }

    /**
     * @param string $field
     * @param string $filename
     * @param array $upload
     * @param array $crop
     * @return bool
     * @deprecated 1.6 For backwards compatibility only, do not use.
     */
    public function cropFile(string $field, string $filename, array $upload, array $crop): bool
    {
        $tmp_dir = $this->getTmpDir();

        Folder::create($tmp_dir);

        $tmp_file = $upload['file']['tmp_name'];
        $basename = basename($tmp_file);

        if (!move_uploaded_file($tmp_file, $tmp_dir . '/' . $basename)) {
            return false;
        }

        $upload['file']['tmp_name'] = $basename;

        if (!isset($this->files[$field])) {
            $this->files[$field] = [];
        }

        // Prepare object for later save
        $upload['file']['name'] = $filename;

        // Deal with crop upload
        $oldUpload = $this->files[$field][$filename] ?? null;
        if ($oldUpload) {
            $originalUpload = $this->files[$field . '/original'][$filename] ?? null;
            if ($originalUpload) {
                // If there is original file already present, remove the modified file
                $originalUpload['crop'] = $crop;
                $this->removeTmpFile($oldUpload['tmp_name'] ?? '');
            } else {
                // Otherwise make the previous file as original
                $oldUpload['crop'] = $crop;
                $this->files[$field . '/original'][$filename] = $oldUpload;
            }
        } else {
            $this->files[$field . '/original'][$filename] = [
                'name' => $filename,
                'type' => $upload['file']['type'],
                'crop' => $crop
            ];
        }

        // Prepare data to be saved later
        $this->files[$field][$filename] = $upload['file'];

        return true;
    }

    /**
     * @return YamlFile
     */
    protected function getTmpIndex(): YamlFile
    {
        // Do not use CompiledYamlFile as the file can change multiple times per second.
        return YamlFile::instance($this->getTmpDir() . '/index.yaml');
    }

    /**
     * @param string $name
     */
    protected function removeTmpFile(string $name): void
    {
        $filename = $this->getTmpDir() . '/' . $name;
        if ($name && is_file($filename)) {
            unlink($filename);
        }
    }

    protected function removeTmpDir(): void
    {
        $tmpDir = $this->getTmpDir();
        if (file_exists($tmpDir)) {
            Folder::delete($tmpDir);
        }
    }
}
