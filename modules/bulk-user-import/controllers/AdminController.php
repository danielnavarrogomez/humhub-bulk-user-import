<?php

namespace modules\bulkUserImport\controllers;

use humhub\modules\admin\components\Controller;
use modules\bulkUserImport\models\UploadForm;
use modules\bulkUserImport\services\CommitService;
use modules\bulkUserImport\services\ImportManager;
use RuntimeException;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\web\Response;
use yii\web\UploadedFile;

class AdminController extends Controller
{
    public function actionIndex()
    {
        $model = new UploadForm();

        if (Yii::$app->request->isPost) {
            $model->file = UploadedFile::getInstance($model, 'file');
            if ($model->validate()) {
                try {
                    $manager = new ImportManager();
                    $result = $manager->handleUpload($model->file);
                    Yii::debug('Bulk user import upload handled, token: ' . $result['token'], __METHOD__);
                    return $this->redirect(['review', 'token' => $result['token']]);
                } catch (RuntimeException $e) {
                    Yii::$app->session->addFlash('danger', $e->getMessage());
                    Yii::error('Bulk user import error: ' . $e->getMessage(), __METHOD__);
                } catch (\Throwable $e) {
                    Yii::$app->session->addFlash('danger', Yii::t('BulkUserImportModule.base', 'Failed to process the XLSX file.'));
                    Yii::error($e, __METHOD__);
                }
            } else {
                Yii::warning('Bulk user import validation failed: ' . json_encode($model->getErrors()), __METHOD__);
            }
        }

        return $this->render('index', [
            'model' => $model,
        ]);
    }

    public function actionDownloadSample(): Response
    {
        $path = Yii::getAlias('@modules/bulkUserImport/resources/sample/bulk_user_import_example.xlsx');
        if (!is_file($path)) {
            throw new RuntimeException('Sample file not found.');
        }

        return Yii::$app->response->sendFile($path, 'bulk_user_import_example.xlsx');
    }

    public function actionReview(string $token)
    {
        $manager = new ImportManager();

        try {
            $forms = $manager->loadForms($token);
        } catch (RuntimeException $e) {
            Yii::$app->session->addFlash('danger', $e->getMessage());
            return $this->redirect(['index']);
        }

        $post = Yii::$app->request->post();
        if (!empty($post)) {
            $this->normalizeFormArrays($forms, $post);
            Model::loadMultiple($forms, $post);

            foreach ($forms as $form) {
                $manager->normalizeForm($form);
            }

            $valid = Model::validateMultiple($forms) && $this->validateUniqueEmails($forms);

            if ($valid) {
                $manager->persistForms($token, $forms);

                if ($this->isCommitRequest($post)) {
                    $commitService = new CommitService();
                    try {
                        $summary = $commitService->commit($forms);
                        $manager->forget($token);
                        return $this->render('summary', [
                            'summary' => $summary,
                        ]);
                    } catch (RuntimeException $e) {
                        Yii::$app->session->addFlash('danger', $e->getMessage());
                    } catch (\Throwable $e) {
                        Yii::$app->session->addFlash('danger', Yii::t('BulkUserImportModule.base', 'An unexpected error occurred while importing users.'));
                        Yii::error($e, __METHOD__);
                    }
                } else {
                    Yii::$app->session->addFlash('success', Yii::t('BulkUserImportModule.base', 'Changes saved. Review and click "Load users" to finish.'));
                    return $this->refresh();
                }
            } elseif ($this->isCommitRequest($post)) {
                Yii::$app->session->addFlash('danger', Yii::t('BulkUserImportModule.base', 'Please resolve the highlighted errors before loading users.'));
            }
        } else {
            Model::validateMultiple($forms);
        }

        $groupOptions = $manager->getGroupOptions();
        asort($groupOptions, SORT_NATURAL | SORT_FLAG_CASE);
        $this->validateUniqueEmails($forms); // highlight duplicates on GET

        return $this->render('review', [
            'forms' => $forms,
            'groupOptions' => $groupOptions,
            'token' => $token,
        ]);
    }

    private function normalizeFormArrays(array $forms, array &$post): void
    {
        if (empty($forms)) {
            return;
        }

        $formName = $forms[0]->formName();
        if (!isset($post[$formName]) || !is_array($post[$formName])) {
            return;
        }

        foreach ($post[$formName] as $index => &$row) {
            if (!is_array($row)) {
                $row = [];
            }

            if (!array_key_exists('groupIds', $row)) {
                $row['groupIds'] = [];
                continue;
            }

            $value = $row['groupIds'];
            if (is_array($value)) {
                $row['groupIds'] = $value;
            } elseif (is_string($value) && $value !== '') {
                $row['groupIds'] = [$value];
            } else {
                $row['groupIds'] = [];
            }
        }
        unset($row);
    }

    private function validateUniqueEmails(array $forms): bool
    {
        $emails = [];
        foreach ($forms as $form) {
            $email = mb_strtolower($form->email);
            if ($email === '') {
                continue;
            }
            $emails[$email] = ($emails[$email] ?? 0) + 1;
        }

        $isValid = true;
        foreach ($forms as $form) {
            $email = mb_strtolower($form->email);
            if ($email !== '' && ($emails[$email] ?? 0) > 1) {
                $form->addError('email', Yii::t('BulkUserImportModule.base', 'Duplicate email in the uploaded list.'));
                $isValid = false;
            }
        }

        return $isValid;
    }

    private function isCommitRequest(array $post): bool
    {
        return isset($post['commit']) && (string)$post['commit'] === '1';
    }
}
