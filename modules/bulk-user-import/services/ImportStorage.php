<?php

namespace modules\bulkUserImport\services;

use Yii;
use yii\helpers\FileHelper;

/**
 * Persists import payloads in the runtime directory.
 */
class ImportStorage
{
    public const STORAGE_ALIAS = '@runtime/bulk-user-import';

    public function save(string $token, array $data): void
    {
        $path = $this->buildPath($token);
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function load(string $token): ?array
    {
        $path = $this->buildPath($token);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function delete(string $token): void
    {
        $path = $this->buildPath($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function buildPath(string $token): string
    {
        return Yii::getAlias(self::STORAGE_ALIAS . '/' . $token . '.json');
    }
}
