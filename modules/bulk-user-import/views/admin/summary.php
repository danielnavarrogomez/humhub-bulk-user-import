<?php

use yii\helpers\Html;

/** @var $this yii\web\View */
/** @var $summary array{created:int, updated:int, rows: array<int, array<string, mixed>>} */

$this->title = Yii::t('BulkUserImportModule.base', 'Import Summary');
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <strong><?= Html::encode($this->title) ?></strong>
    </div>
    <div class="panel-body">
        <p><?= Yii::t('BulkUserImportModule.base', 'Created users: {count}', ['count' => $summary['created']]); ?></p>
        <p><?= Yii::t('BulkUserImportModule.base', 'Updated users: {count}', ['count' => $summary['updated']]); ?></p>

        <div class="table-responsive">
            <table class="table table-striped table-condensed">
                <thead>
                <tr>
                    <th><?= Yii::t('BulkUserImportModule.base', 'Email') ?></th>
                    <th><?= Yii::t('BulkUserImportModule.base', 'Action') ?></th>
                    <th><?= Yii::t('BulkUserImportModule.base', 'Groups added') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary['rows'] as $row): ?>
                    <tr>
                        <td><?= Html::encode($row['email']) ?></td>
                        <td><?= Html::encode($row['type'] === 'create'
                                ? Yii::t('BulkUserImportModule.base', 'Created')
                                : Yii::t('BulkUserImportModule.base', 'Updated')) ?></td>
                        <td><?= Html::encode(empty($row['groupsAdded']) ? '-' : implode(', ', $row['groupsAdded'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= Html::a(Yii::t('BulkUserImportModule.base', 'Back to upload'), ['index'], ['class' => 'btn btn-primary']) ?>
    </div>
</div>
