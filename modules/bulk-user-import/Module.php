<?php

namespace modules\bulkUserImport;

use Yii;
use humhub\components\Module as BaseModule;
use yii\helpers\Url;

/**
 * Bulk User Import module.
 */
class Module extends BaseModule
{
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // Ensure the PSR-4 namespace for this module resolves correctly.
        Yii::setAlias('@modules/bulkUserImport', __DIR__);

        Yii::$app->i18n->translations['BulkUserImportModule.*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'basePath' => '@modules/bulkUserImport/messages',
            'sourceLanguage' => 'en-US',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigUrl()
    {
        return Url::to(['/bulk-user-import/admin/index']);
    }
}
