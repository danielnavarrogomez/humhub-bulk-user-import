<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var $this yii\web\View */
/** @var $forms modules\bulkUserImport\models\ImportRowForm[] */
/** @var $groupOptions array<int, string> */
/** @var $token string */

$this->title = Yii::t('BulkUserImportModule.base', 'Review Imported Users');
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <strong><?= Html::encode($this->title) ?></strong>
    </div>
    <div class="panel-body">
        <style>
            .bulk-import-table .row-number {
                font-size: 1.2em;
                font-weight: 600;
            }

            .bulk-import-table .status-label {
                margin-top: 6px;
            }

            .bulk-import-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 8px 12px;
                align-items: start;
            }

            .bulk-import-grid__field label {
                font-weight: 600;
                margin-bottom: 2px;
                display: block;
                color: #333;
            }

            .bulk-import-grid__field .form-control {
                padding: 6px 8px;
                font-size: 0.95em;
                min-height: 34px;
            }

            .bulk-import-grid__field--email {
                grid-column: span 2;
            }

            .bulk-import-grid__field--groups {
                grid-column: 1 / -1;
            }

            .bulk-import-grid__field--groups .form-control {
                min-height: 100px;
            }

            .bulk-import-grid__notes {
                grid-column: 1 / -1;
                background: #fdfdfd;
                border: 1px solid #e6e6e6;
                padding: 6px 8px;
                border-radius: 3px;
                font-size: 0.9em;
                margin-top: 2px;
            }
        </style>

        <p><?= Yii::t('BulkUserImportModule.base', 'Review the normalized data below. Adjust names, emails, or group memberships before loading the users. Existing users are highlighted and will be updated.'); ?></p>

<?php $formWidget = ActiveForm::begin(['id' => 'bulk-import-review-form']); ?>
<?php
$errorSummary = [];
foreach ($forms as $formModel) {
    if ($formModel->hasErrors()) {
        $messages = array_values(array_unique($formModel->getFirstErrors()));
        $errorSummary[$formModel->rowNumber] = implode('; ', $messages);
    }
}
$hasErrors = !empty($errorSummary);
?>

<?php if ($hasErrors): ?>
    <div class="alert alert-danger">
        <strong><?= Yii::t('BulkUserImportModule.base', 'Fix the highlighted issues before loading users.'); ?></strong>
        <ul>
            <?php foreach ($errorSummary as $rowNumber => $message): ?>
                <li><?= Html::encode(Yii::t('BulkUserImportModule.base', 'Row {row} contains errors: {errors}', [
                        'row' => $rowNumber,
                        'errors' => $message,
                    ])) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

        <div class="table-responsive bulk-import-table">
            <table class="table table-striped table-bordered">
                <thead>
                <tr>
                    <th><?= Yii::t('BulkUserImportModule.base', 'Row') ?></th>
                    <th><?= Yii::t('BulkUserImportModule.base', 'Details') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($forms as $index => $rowModel): ?>
                    <?php
                    $rowClasses = [];
                    if ($rowModel->hasErrors()) {
                        $rowClasses[] = 'danger';
                    } elseif ($rowModel->existingUserId !== null) {
                        $rowClasses[] = 'warning';
                    }

                    $existingGroups = [];
                    if (!empty($rowModel->existingGroupIds)) {
                        $existingGroups = array_intersect_key($groupOptions, array_flip($rowModel->existingGroupIds));
                    }
                    ?>
                    <tr class="<?= implode(' ', $rowClasses) ?>">
                        <td class="bulk-import-table__rownumber">
                            <div class="row-number">
                                <?= Html::encode($rowModel->rowNumber) ?>
                            </div>
                            <div class="status-label">
                                <?= Html::tag('span', Html::encode($rowModel->getStatusText()), [
                                    'class' => $rowModel->existingUserId !== null ? 'label label-warning' : 'label label-success',
                                ]) ?>
                            </div>
                        </td>
                        <td>
                            <div class="bulk-import-grid">

                                <div class="bulk-import-grid__field">
                                    <label><?= Yii::t('BulkUserImportModule.base', 'First name') ?></label>
                                    <?= Html::activeTextInput($rowModel, "[$index]firstName", ['class' => 'form-control']) ?>
                                    <?= Html::error($rowModel, "[$index]firstName", ['class' => 'help-block help-block-error']) ?>
                                </div>

                                <div class="bulk-import-grid__field">
                                    <label><?= Yii::t('BulkUserImportModule.base', 'Last name') ?></label>
                                    <?= Html::activeTextInput($rowModel, "[$index]lastName", ['class' => 'form-control']) ?>
                                    <?= Html::error($rowModel, "[$index]lastName", ['class' => 'help-block help-block-error']) ?>
                                </div>

                                <div class="bulk-import-grid__field bulk-import-grid__field--email">
                                    <label><?= Yii::t('BulkUserImportModule.base', 'Email') ?></label>
                                    <?= Html::activeTextInput($rowModel, "[$index]email", ['class' => 'form-control']) ?>
                                    <?= Html::error($rowModel, "[$index]email", ['class' => 'help-block help-block-error']) ?>
                                </div>

                                <div class="bulk-import-grid__field bulk-import-grid__field--groups">
                                    <label><?= Yii::t('BulkUserImportModule.base', 'Groups') ?></label>
                                    <?= Html::activeListBox(
                                        $rowModel,
                                        "[$index]groupIds",
                                        $groupOptions,
                                        [
                                            'class' => 'form-control',
                                            'multiple' => true,
                                            'size' => 8,
                                        ]
                                    ) ?>
                                    <?= Html::error($rowModel, "[$index]groupIds", ['class' => 'help-block help-block-error']) ?>
                                </div>

                                <div class="bulk-import-grid__notes">
                                    <?php if ($existingGroups): ?>
                                        <p><?= Yii::t('BulkUserImportModule.base', 'Current groups: {groups}', [
                                                'groups' => Html::encode(implode(', ', $existingGroups)),
                                            ]) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($rowModel->pendingGroupTokens)): ?>
                                        <p class="text-warning">
                                            <?= Yii::t('BulkUserImportModule.base', 'Unknown groups: {groups} (will be ignored)', [
                                                'groups' => Html::encode(implode(', ', $rowModel->pendingGroupTokens)),
                                            ]) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($rowModel->hasErrors()): ?>
                                        <p class="text-danger"><?= Html::encode(implode('; ', array_values($rowModel->getFirstErrors()))) ?></p>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-group">
            <?= Html::submitButton(Yii::t('BulkUserImportModule.base', 'Save changes'), [
                'class' => 'btn btn-default',
                'name' => 'commit',
                'value' => '0',
            ]) ?>
            <?= Html::submitButton(Yii::t('BulkUserImportModule.base', 'Load users'), [
                'class' => 'btn btn-primary',
                'name' => 'commit',
                'value' => '1',
                'data-confirm' => Yii::t('BulkUserImportModule.base', 'Are you sure you want to load these users?'),
                'disabled' => $hasErrors,
            ]) ?>
            <?= Html::a(Yii::t('BulkUserImportModule.base', 'Start over'), ['index'], ['class' => 'btn btn-link']) ?>
        </div>

<?php ActiveForm::end(); ?>

<?php if ($hasErrors): ?>
    <p class="text-danger">
        <?= Yii::t('BulkUserImportModule.base', 'Fix the highlighted issues before loading users.'); ?>
    </p>
<?php endif; ?>
    </div>
</div>
