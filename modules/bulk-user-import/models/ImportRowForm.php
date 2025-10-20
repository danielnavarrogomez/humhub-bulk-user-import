<?php

namespace modules\bulkUserImport\models;

use humhub\modules\user\models\User;
use modules\bulkUserImport\services\GroupResolver;
use Yii;
use yii\base\Model;
use yii\db\Expression;

class ImportRowForm extends Model
{
    public int $rowNumber;
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public array $groupIds = [];

    public ?int $existingUserId = null;
    public array $existingGroupIds = [];
    public array $pendingGroupTokens = [];

    private GroupResolver $groupResolver;

    public function __construct(GroupResolver $resolver, array $config = [])
    {
        $this->groupResolver = $resolver;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['rowNumber'], 'integer'],
            [['firstName', 'lastName', 'email'], 'trim'],
            [['firstName', 'lastName', 'email'], 'required'],
            [['firstName', 'lastName'], 'string', 'max' => 100, 'tooLong' => Yii::t('BulkUserImportModule.base', 'Names can have a maximum length of 100 characters.')],
            ['email', 'string', 'max' => 150],
            ['email', 'email'],
            ['groupIds', 'each', 'rule' => ['integer']],
            ['pendingGroupTokens', 'each', 'rule' => ['string']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'firstName' => Yii::t('BulkUserImportModule.base', 'First name'),
            'lastName' => Yii::t('BulkUserImportModule.base', 'Last name'),
            'email' => Yii::t('BulkUserImportModule.base', 'Email'),
            'groupIds' => Yii::t('BulkUserImportModule.base', 'Groups'),
        ];
    }

    public function detectExistingUser(): void
    {
        $email = mb_strtolower($this->email);
        $existing = User::find()
            ->where(new Expression('LOWER(email) = :email'), [':email' => $email])
            ->orWhere(new Expression('LOWER(username) = :username'), [':username' => $email])
            ->one();

        if ($existing) {
            $this->existingUserId = (int)$existing->id;
            $this->existingGroupIds = $existing->getGroups()->select('id')->column();
        } else {
            $this->existingUserId = null;
            $this->existingGroupIds = [];
        }
    }

    public function markPendingGroups(array $tokens): void
    {
        $this->pendingGroupTokens = $tokens;
    }

    public function getGroupLabels(): array
    {
        return $this->groupResolver->getNames($this->groupIds);
    }

    public function getStatusText(): string
    {
        if ($this->existingUserId !== null) {
            return Yii::t('BulkUserImportModule.base', 'Will update existing user');
        }

        return Yii::t('BulkUserImportModule.base', 'Will create new user');
    }

    public function toPayload(): array
    {
        return [
            'rowNumber' => $this->rowNumber,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => mb_strtolower($this->email),
            'groupIds' => array_values(array_map('intval', $this->groupIds)),
            'pendingTokens' => $this->pendingGroupTokens,
        ];
    }

    public function loadPayload(array $payload): void
    {
        $this->rowNumber = (int)($payload['rowNumber'] ?? 0);
        $this->firstName = (string)($payload['firstName'] ?? '');
        $this->lastName = (string)($payload['lastName'] ?? '');
        $this->email = (string)($payload['email'] ?? '');
        $this->groupIds = array_values(array_map('intval', $payload['groupIds'] ?? []));
        $this->pendingGroupTokens = array_values($payload['pendingTokens'] ?? []);
    }
}
