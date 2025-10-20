<?php

namespace modules\bulkUserImport;

use humhub\modules\admin\widgets\AdminMenu;
use Yii;

class Events
{
    public static function onAdminMenuInit($event): void
    {
        if (!Yii::$app->user->isAdmin()) {
            return;
        }

        $event->sender->addItem([
            'label' => Yii::t('BulkUserImportModule.base', 'Bulk User Import'),
            'url' => ['/bulk-user-import/admin/index'],
            'group' => 'manage-users',
            'icon' => '<i class="fa fa-users"></i>',
            'sortOrder' => 210,
        ]);
    }
}
