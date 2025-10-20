<?php

return [
    'id' => 'bulk-user-import',
    'class' => 'modules\bulkUserImport\Module',
    'namespace' => 'modules\bulkUserImport',
    'events' => [
        [
            'class' => \humhub\modules\admin\widgets\AdminMenu::class,
            'event' => \humhub\modules\admin\widgets\AdminMenu::EVENT_INIT,
            'callback' => ['modules\bulkUserImport\Events', 'onAdminMenuInit'],
        ],
    ],
];
