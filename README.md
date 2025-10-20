# HumHub Bulk User Import Module

> **Screenshots**: _Add UI images at `docs/screenshots/01-upload.png`, `docs/screenshots/02-review.png`, and `docs/screenshots/03-summary.png`._

## Overview

This custom HumHub module lets administrators import large batches of users from an `.xlsx` workbook. Uploads are normalised, validated, and reviewable before any accounts or group memberships are created or updated. The module works without additional PHP extensions – XLSX parsing is implemented with standard libraries.

## Key Features

- Accepts `.xlsx` files with the columns `name`, `last name`, `email`, `groups` (additional columns are ignored).
- Normalises casing (title case names, lower case emails) and strips whitespace.
- Resolves group assignments by name or numeric ID (case-insensitive, multiple groups per row).
- Detects existing users by email **or** username (so re-importing the same address updates instead of duplicating).
- Flags duplicate emails, invalid addresses, unknown groups, missing required data, and overly long names.
- Prevents the import while any row still contains validation errors.
- Provides an audit summary of created and updated users once the import succeeds.
- Ships with sample workbooks, including `bulk_user_import_edge_cases.xlsx`, to stress-test edge scenarios.

## Repository Layout

```
humhub-bulk-user-import/
├── docker/
│   ├── docker-compose.yml         # optional dev stack for local HumHub testing
│   └── humhub.env.example         # environment defaults (copy to humhub.env)
├── modules/
│   └── bulk-user-import/          # HumHub module code
└── README.md                      # this file
```

## Requirements

- HumHub 1.14 or newer.
- PHP 8.1+ with standard extensions (no extra XLSX libraries required).
- Access to the HumHub filesystem to copy the module or create symlinks.

## Installation (Production / Existing HumHub)

1. Copy `modules/bulk-user-import` into your HumHub instance under `protected/modules/bulk-user-import`.
2. Clear caches if necessary: `php protected/yii cache/flush-all`.
3. Enable the module via **Administration → Modules** or run:
   ```bash
   php protected/yii module/enable bulk-user-import
   ```
4. Open **Administration → Users → Bulk User Import** to use the tool.

## Development Environment (Optional)

A lightweight Docker stack is bundled for local testing. It spins up HumHub + MariaDB + Redis and mounts the module from this repository.

> **Note**: The compose file expects to run from the HumHub project root. Adjust volume paths if you keep the repository elsewhere.

```bash
# 1. Copy environment overrides
cp docker/humhub.env.example docker/humhub.env

# 2. Launch the stack
docker compose -f docker/docker-compose.yml up -d

# 3. Follow logs
docker compose -f docker/docker-compose.yml logs -f humhub
```

To share the module between the repo and your HumHub install without duplicating files, create symlinks:

```bash
ln -s /path/to/humhub-bulk-user-import/modules/bulk-user-import \
      /path/to/humhub/protected/modules-dev/bulk-user-import

# (Optional) link docker config for the same compose file
ln -s /path/to/humhub-bulk-user-import/docker \
      /path/to/humhub/docker/bulk-user-import
```

## Excel Format

| Column       | Description                                                                 |
|--------------|-----------------------------------------------------------------------------|
| `name`       | First name. Normalised to title case. Required, max 100 characters.         |
| `last name`  | Surname. Normalised to title case. Required, max 100 characters.            |
| `email`      | Email address. Lowercased, spaces removed. Required and must be unique.     |
| `groups`     | Optional; semicolon or comma separated list of group IDs or names.          |

Additional columns are allowed and ignored. Unknown groups appear as warnings during review and are skipped unless corrected.

## Validation Behaviour

- Empty `name`, `last name`, or `email` → flagged as errors.
- Emails must pass PHP’s email validator and be unique (checked against existing users and within the sheet).
- Names longer than 100 characters trigger a validation error.
- Unknown groups are listed as warnings; they do not block the import but remain editable before confirmation.
- The **Load users** button is automatically disabled while any error persists.

## Sample Data

- `modules/bulk-user-import/resources/sample/bulk_user_import_example.xlsx` – simple starter dataset.
- `modules/bulk-user-import/resources/sample/bulk_user_import_example_v2.xlsx` – larger mix of new + existing users.
- `modules/bulk-user-import/resources/sample/bulk_user_import_edge_cases.xlsx` – edge scenarios (accents, invalid emails, duplicates, etc.).

## Usage Workflow

1. Navigate to **Administration → Users → Bulk User Import**.
2. Upload an `.xlsx` file following the required headers.
3. Review the normalised dataset. Edit any cells directly in the table.
4. Resolve warnings/errors. The page lists affected rows in a red alert until all issues are cleared.
5. Click **Load users**. A summary page confirms how many accounts were created or updated and which groups were added.

## Updating / Maintenance

- Bump module metadata (`module.json`) when you release new versions.
- Keep this README updated with any breaking changes or additional requirements.
- Provide release notes in `modules/bulk-user-import/docs/CHANGELOG.md` (create the file when needed).

## Testing Checklist

- [ ] Import fresh users with group IDs only.
- [ ] Import existing users to confirm updates instead of duplicates.
- [ ] Upload the `edge_cases` workbook to verify validation feedback.
- [ ] Disable the module and re-enable to confirm configuration persists.

## License

Include your preferred license file (e.g. `LICENSE`) if you plan to distribute the module publicly.

