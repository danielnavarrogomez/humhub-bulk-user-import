<?php

namespace modules\bulkUserImport\services;

use humhub\modules\user\models\Group;
use humhub\modules\user\models\Profile;
use humhub\modules\user\models\User;
use modules\bulkUserImport\models\ImportRowForm;
use RuntimeException;
use Yii;

class CommitService
{
    /**
     * @var array<int, Group>
     */
    private array $groupCache = [];

    /**
     * @param ImportRowForm[] $forms
     * @return array{created: int, updated: int, rows: array<int, array<string, mixed>>}
     */
    public function commit(array $forms): array
    {
        $created = 0;
        $updated = 0;
        $rows = [];

        foreach ($forms as $form) {
            if ($form->existingUserId !== null) {
                $user = User::findOne($form->existingUserId);
                if ($user === null) {
                    throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'User could not be loaded for update: {email}', [
                        'email' => $form->email,
                    ]));
                }

                $this->updateExistingUser($user, $form);
                $updated++;
                $rows[] = [
                    'email' => $form->email,
                    'type' => 'update',
                    'userId' => $user->id,
                    'groupsAdded' => $this->applyGroups($user, $form),
                ];
            } else {
                $user = $this->createUser($form);
                $created++;
                $rows[] = [
                    'email' => $form->email,
                    'type' => 'create',
                    'userId' => $user->id,
                    'groupsAdded' => $this->applyGroups($user, $form),
                ];
            }
        }

        return ['created' => $created, 'updated' => $updated, 'rows' => $rows];
    }

    private function updateExistingUser(User $user, ImportRowForm $form): void
    {
        $profile = $user->profile;
        if ($profile === null) {
            $profile = new Profile();
            $profile->user_id = $user->id;
        }

        $profile->scenario = Profile::SCENARIO_EDIT_ADMIN;
        $profile->firstname = $form->firstName;
        $profile->lastname = $form->lastName;

        if (!$profile->save()) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'Unable to update profile for {email}.', [
                'email' => $form->email,
            ]));
        }
    }

    private function createUser(ImportRowForm $form): User
    {
        $user = new User();
        $user->scenario = User::SCENARIO_EDIT_ADMIN;
        $user->username = $this->generateUsername($form->email);
        $user->email = $form->email;
        $user->status = User::STATUS_ENABLED;

        if (!$user->save()) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'Unable to create user: {email}', [
                'email' => $form->email,
            ]));
        }

        $profile = new Profile();
        $profile->scenario = Profile::SCENARIO_EDIT_ADMIN;
        $profile->user_id = $user->id;
        $profile->firstname = $form->firstName;
        $profile->lastname = $form->lastName;
        if (!$profile->save()) {
            throw new RuntimeException(Yii::t('BulkUserImportModule.base', 'Unable to create profile for {email}.', [
                'email' => $form->email,
            ]));
        }

        return $user;
    }

    private function applyGroups(User $user, ImportRowForm $form): array
    {
        $currentGroupIds = $user->getGroups()->select('id')->column();
        $currentGroupIds = array_map('intval', $currentGroupIds);
        $toAdd = array_diff($form->groupIds, $currentGroupIds);

        $addedNames = [];
        foreach ($toAdd as $groupId) {
            $group = $this->getGroup($groupId);
            if ($group === null) {
                continue;
            }

            $group->addUser($user);
            $addedNames[] = $group->name;
        }

        return $addedNames;
    }

    private function generateUsername(string $email): string
    {
        $email = trim($email);

        $userModule = Yii::$app->getModule('user');
        $maxLength = $userModule && property_exists($userModule, 'maximumUsernameLength')
            ? (int)$userModule->maximumUsernameLength
            : 50;
        $minLength = $userModule && property_exists($userModule, 'minimumUsernameLength')
            ? (int)$userModule->minimumUsernameLength
            : 1;

        $base = $this->sanitizeUsernameCandidate($email);
        if ($base === '') {
            $base = 'user';
        }

        $base = mb_substr($base, 0, max($maxLength, 1), 'UTF-8');
        if (mb_strlen($base, 'UTF-8') < $minLength) {
            $base = str_pad($base, $minLength, '0');
        }

        $candidate = $base;
        $suffix = 1;
        while (User::find()->where(['username' => $candidate])->exists()) {
            $suffixStr = '_' . $suffix;
            $truncateLength = max($maxLength - strlen($suffixStr), 1);
            $candidate = mb_substr($base, 0, $truncateLength, 'UTF-8') . $suffixStr;
            $suffix++;
        }

        return $candidate;
    }

    private function sanitizeUsernameCandidate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $normalized = mb_strtolower($value, 'UTF-8');
        $sanitized = preg_replace('/[^\p{L}\d_\-@.]+/u', '', $normalized);

        return $sanitized ?? '';
    }

    private function getGroup(int $id): ?Group
    {
        if (!isset($this->groupCache[$id])) {
            $this->groupCache[$id] = Group::findOne($id);
        }

        return $this->groupCache[$id];
    }
}
