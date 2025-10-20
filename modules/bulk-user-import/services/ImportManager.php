<?php

namespace modules\bulkUserImport\services;

use modules\bulkUserImport\models\ImportRowForm;
use RuntimeException;
use Yii;
use yii\web\UploadedFile;

class ImportManager
{
    private GroupResolver $groupResolver;
    private XlsxParser $parser;
    private ImportStorage $storage;

    public function __construct()
    {
        $this->groupResolver = new GroupResolver();
        $this->parser = new XlsxParser();
        $this->storage = new ImportStorage();
    }

    /**
     * @return array{token: string, forms: ImportRowForm[]}
     */
    public function handleUpload(UploadedFile $file): array
    {
        $parsed = $this->parser->parse($file->tempName);
        $headerMap = $this->buildHeaderMap($parsed['headers']);

        $forms = [];
        foreach ($parsed['rows'] as $index => $row) {
            $excelRowNumber = $index + 2; // headers occupy row 1

            $firstName = $this->normalizeName($row[$headerMap['name']] ?? '');
            $lastName = $this->normalizeName($row[$headerMap['last name']] ?? '');
            $email = $this->normalizeEmail($row[$headerMap['email']] ?? '');
            $groupCell = (string) ($row[$headerMap['groups']] ?? '');
            $groupTokens = $this->extractGroupTokens($groupCell);

            $form = new ImportRowForm($this->groupResolver);
            $form->rowNumber = $excelRowNumber;
            $form->firstName = $firstName;
            $form->lastName = $lastName;
            $form->email = $email;

            $resolvedGroups = [];
            $pending = [];

            foreach ($groupTokens as $token) {
                $resolved = $this->groupResolver->resolveToken($token);
                if ($resolved !== null) {
                    $resolvedGroups[$resolved] = $resolved;
                } else {
                    $pending[] = $token;
                }
            }

            $form->groupIds = array_values($resolvedGroups);
            $form->markPendingGroups($pending);
            $form->detectExistingUser();

            $forms[] = $form;
        }

        if (empty($forms)) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'The XLSX file does not contain any user rows.'));
        }

        $token = $this->generateToken();
        $this->storage->save($token, [
            'createdAt' => time(),
            'originalName' => $file->name,
            'rows' => array_map(static fn(ImportRowForm $form) => $form->toPayload(), $forms),
        ]);

        return ['token' => $token, 'forms' => $forms];
    }

    /**
     * @return ImportRowForm[]
     */
    public function loadForms(string $token): array
    {
        $payload = $this->storage->load($token);
        if ($payload === null) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'The import session could not be found.'));
        }

        $forms = [];
        foreach ($payload['rows'] ?? [] as $rowPayload) {
            $form = new ImportRowForm($this->groupResolver);
            $form->loadPayload($rowPayload);
            $form->detectExistingUser();
            $forms[] = $form;
        }

        if (empty($forms)) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'The import session is empty.'));
        }

        return $forms;
    }

    public function persistForms(string $token, array $forms): void
    {
        $payload = $this->storage->load($token);
        if ($payload === null) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'Unable to persist data, session missing.'));
        }

        $payload['rows'] = array_map(static fn(ImportRowForm $form) => $form->toPayload(), $forms);
        $this->storage->save($token, $payload);
    }

    public function forget(string $token): void
    {
        $this->storage->delete($token);
    }

    public function getGroupOptions(): array
    {
        return $this->groupResolver->getAllOptions();
    }

    public function normalizeForm(ImportRowForm $form): void
    {
        $form->firstName = $this->normalizeName($form->firstName);
        $form->lastName = $this->normalizeName($form->lastName);
        $form->email = $this->normalizeEmail($form->email);
        $form->groupIds = array_values(array_unique(array_map('intval', $form->groupIds)));
        $form->detectExistingUser();
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = mb_strtolower(trim((string) $header));
            $map[$normalized] = $index;
        }

        $required = ['name', 'last name', 'email', 'groups'];
        foreach ($required as $column) {
            if (!isset($map[$column])) {
                throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'Missing required column: {column}', [
                    'column' => $column,
                ]));
            }
        }

        return $map;
    }

    private function normalizeName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value);
        $value = mb_strtolower($value, 'UTF-8');

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizeEmail(string $value): string
    {
        $value = trim($value);
        $value = str_replace(' ', '', $value);
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * @return string[]
     */
    private function extractGroupTokens(string $cell): array
    {
        $cell = trim($cell);
        if ($cell === '') {
            return [];
        }

        $parts = preg_split('/[;,]+/', $cell);
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
