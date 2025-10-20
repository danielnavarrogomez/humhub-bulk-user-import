<?php

namespace modules\bulkUserImport\models;

use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

class UploadForm extends Model
{
    public const MAX_FILE_SIZE = 5242880; // 5 MiB

    /**
     * @var UploadedFile|null
     */
    public ?UploadedFile $file = null;

    public function rules(): array
    {
        return [
            [['file'], 'file', 'skipOnEmpty' => false, 'extensions' => ['xlsx'], 'maxSize' => self::MAX_FILE_SIZE],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'file' => Yii::t('BulkUserImportModule.base', 'XLSX file'),
        ];
    }
}
