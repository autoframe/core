# Git Exec Hook (`Autoframe\\Core\\GitExecHook`)

> **Purpose**: document webhook parsing and git command automation helpers from `src/GitExecHook/`.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\GitExecHook`
- **Source root**: `src/GitExecHook/`
- **Main classes**:
  - `AfrGitHook` (GitHub webhook parser/validator + event predicates)
  - `AfrGitExec` (Git CLI wrapper + branch automation flows)

### 1.1 Class map

| Class | Responsibility |
|---|---|
| `AfrGitHook` | Reads webhook headers/body, validates signature, exposes event/branch/commit helpers, and dispatch convenience for push-on-master flows. |
| `AfrGitExec` | Executes git commands for a target repository, supports add/commit/push/pull/fetch and high-level deployment-style workflows. |

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: git-exec-hook
namespace: Autoframe\\Core\\GitExecHook
source_dir: src/GitExecHook
classes:
  - AfrGitHook
  - AfrGitExec
features:
  - github_webhook_header_parsing
  - hmac_signature_validation_sha1_sha256
  - event_predicates_push_release_pull_request_ping
  - branch_detection_and_master_branch_checks
  - git_cli_command_execution
  - commit_push_pull_fetch_automation
  - all_in_one_push_and_master_sync_workflow
integration_patterns:
  - webhook_driven_deployments
  - branch_guarded_automation
  - ci_cd_git_ops_from_php
```

---

## 3) `AfrGitHook` overview

### 3.1 What it does

- Loads request headers (including fallback behavior where `getallheaders()` is unavailable).
- Reads JSON payload from `php://input`.
- Validates GitHub signature headers:
  - `x-hub-signature` (sha1)
  - `x-hub-signature-256` (sha256)
- Exposes event helpers:
  - `isPush()`, `isRelease()`, `isPullRequest()`, `isPing()`
  - merge/fix checks and branch/master checks.

### 3.2 Secret resolution order

When constructor secret is empty, it checks:

1. `X_HUB_SIGNATURE` constant
2. `X_HUB_SIGNATURE_256` constant
3. `$_ENV['X_HUB_SIGNATURE']`
4. `$_ENV['X_HUB_SIGNATURE_256']`

### 3.3 Common helpers

| Method | Purpose |
|---|---|
| `getEventType()` | Returns lowercase GitHub event type. |
| `getBranchName()` | Extracts branch from `ref` payload (`refs/heads/*`). |
| `isOnMasterBranch()` | Checks branch against `AfrGitExec::$defaultMasterBranchNames`. |
| `isPushOnMasterBranch()` | True for push/release/merge-branch events on master-like branch. |
| `getCommitInfo()` | Normalized commit metadata list. |
| `handlePushOnMasterBranch(AfrGitExec $gitExec)` | Executes all-in-one git workflow when branch/event conditions match. |

---

## 4) `AfrGitExec` overview

### 4.1 What it does

- Runs git commands under a repo directory using either:
  - `git -C <repo> ...` (newer git), or
  - `cd <repo> && git ...` (older git fallback).
- Provides wrappers for frequent operations:
  - status/diff/fetch/pull/push
  - add+commit
  - checkout branch/new branch
  - reset/clean utilities
- Provides higher-level automation methods:
  - `allInOnePushCurrentThenSwitchToMasterPullAddCommitAndPush()`
  - `hookMasterCheckout(...)`

### 4.2 Return contract for command methods

Most command methods return a normalized array structure:

```php
[
  'gitExitSuccess' => bool,
  'gitExitCode' => int,
  'gitOutputLines' => array,
  'execReturn' => string,
  'call' => string,
]
```

### 4.3 Master branch behavior

- Default master candidates: `['main', 'master']`.
- `getMasterBranchName()` detects existing branch from local repo list.
- `checkoutMasterBranch()` switches to detected master branch.

---

## 5) PHP examples

### 5.1 Minimal webhook endpoint (validate + react)

```php
<?php

use Autoframe\Core\GitExecHook\AfrGitExec;
use Autoframe\Core\GitExecHook\AfrGitHook;

$hook = new AfrGitHook(
    sGitHookSecret: $_ENV['X_HUB_SIGNATURE_256'] ?? '',
    sExceptionClass: \RuntimeException::class,
    bDumpToFile: false,
    bSkipHeaderShaChecks: false
);

$git = new AfrGitExec('/var/www/my-repo');

if ($hook->isPushOnMasterBranch()) {
    $log = $hook->handlePushOnMasterBranch($git);
    $hook->replyToGithub(200, 'master push handled');
}
```

### 5.2 Manual repository automation

```php
<?php

use Autoframe\Core\GitExecHook\AfrGitExec;

$git = new AfrGitExec('/srv/app/repo');

$branch = $git->getCurrentBranchName();
$ops = $git->gitAddCommitAndPush(
    'Automated commit ' . gmdate('Y-m-d H:i:s') . ' GMT',
    $branch
);

print_r($ops);
```

### 5.3 Configure git identity + pull latest

```php
<?php

use Autoframe\Core\GitExecHook\AfrGitExec;

$git = new AfrGitExec('/srv/app/repo');
$git->setGitConfigDefault('autoframe-bot', 'bot@example.com', true);
$git->gitFetch('--all');
$git->gitPull();
```

---

## 6) Operational notes

- Webhook signature validation requires the raw request body to match what GitHub signed.
- Prefer `x-hub-signature-256` secrets when possible.
- For automation safety, always verify branch and event type before running destructive git commands.
- Command wrappers expose raw outputs, so callers should inspect `gitExitSuccess` before chaining sensitive steps.

---

## 7) Suggested tests/checks

| Concern | Suggested checks |
|---|---|
| Header parsing fallback | Simulate environments with/without `getallheaders()`. |
| Signature validation | Valid/invalid HMAC for sha1/sha256 payloads. |
| Event routing | push/release/pull_request/ping classification. |
| Branch detection | refs parsing and master candidate matching. |
| Git command execution | verify array return contract and exit-code handling. |

---

## 8) Contribution checklist

Before changing `src/GitExecHook/*`:

- [ ] Keep webhook signature validation behavior documented.
- [ ] Update examples if constructor/runtime assumptions change.
- [ ] Keep command return contract section in sync.
- [ ] Document any new high-level git workflow methods.
