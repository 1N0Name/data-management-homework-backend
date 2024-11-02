<?php

namespace App\Utils;

class FileUtils
{
    /**
     * Загрузка нового логотипа агента
     *
     * @param int $id ID агента
     * @param array $file массив данных загружаемого файла
     * @param string $uploadDirBase базовый путь до директории загрузки
     * @return string путь к новому файлу
     * @throws \Exception в случае ошибки загрузки
     */
    public static function handleLogoUpload(int $id, array $file, string $uploadDirBase): string
    {
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];

        if (!array_key_exists($file['type'], $allowedTypes)) {
            throw new \Exception('Invalid file type for logo', 400);
        }

        $uploadDir = $uploadDirBase . '/uploads/logo/';
        is_dir($uploadDir) || mkdir($uploadDir, 0777, true);

        $extension = $allowedTypes[$file['type']];
        $newFileName = "agent_{$id}.{$extension}";
        $newFilePath = $uploadDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $newFilePath)) {
            throw new \Exception('Error saving the new logo file', 500);
        }

        return '/uploads/logo/' . $newFileName;
    }

    /**
     * Удаление существующего логотипа
     *
     * @param string $existingLogoPath полный путь до существующего логотипа
     */
    public static function deleteExistingLogo(string $existingLogoPath): void
    {
        if (file_exists($existingLogoPath)) {
            unlink($existingLogoPath);
        }
    }
}