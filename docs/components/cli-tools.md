# CLI Tools (`Autoframe\\Core\\CliTools`)

> **Purpose**: document the CLI/runtime utility classes under `src/CliTools`, with a structure that is easy for humans and AI systems to index.

---

## 1) Component Snapshot

- **Namespace**: `Autoframe\\Core\\CliTools`
- **Source root**: `src/CliTools/`
- **Primary domains**:
  - runtime context detection (CLI vs HTTP)
  - terminal UX (prompt menu + ANSI text styles/colors)
  - command execution capability checks
  - getopt-style argument parsing
  - temp directory strategy for CLI/runtime workflows
  - vendor/base path helpers

### 1.1 Class map

| Class | Responsibility |
|---|---|
| `AfrCheckExec` | Detect whether `exec`/`shell_exec`/`popen`/`proc_open` family is available and callable. |
| `AfrCliHttpDetect` | Detect and classify runtime context (CLI, HTTP, HTTPS, proxy/load-balancer forwarded states). |
| `AfrCliPromptMenu` | Interactive CLI choice menu/prompt helper. |
| `AfrCliTextColors` | ANSI text styling/color/background formatting for terminal output. |
| `AfrGetOpt` | Parse short/long command options and remaining args (getopt-like behavior). |
| `AfrSysTempDir` | Resolve stable temp dir paths across contexts/users/installations. |
| `AfrVendorDir` | Wrapper for vendor/base/composer path helpers. |

---

## 2) AI-Friendly Index (Machine-Readable)

```yaml
doc_id: cli-tools
namespace: Autoframe\\Core\\CliTools
source_dir: src/CliTools
classes:
  - AfrCheckExec
  - AfrCliHttpDetect
  - AfrCliPromptMenu
  - AfrCliTextColors
  - AfrGetOpt
  - AfrSysTempDir
  - AfrVendorDir
capabilities:
  - detect_cli_or_http_context
  - detect_https_native_or_forwarded
  - interactive_cli_prompt_menu
  - terminal_text_styling
  - command_execution_capability_checks
  - option_parsing_short_and_long_flags
  - resilient_temp_directory_resolution
  - composer_vendor_path_helpers
usage_modes:
  - cli_runtime
  - bootstrap_runtime_detection
  - deployment_utility_scripts
```

---

## 3) Quick Usage Examples

### 3.1 Runtime detection

```php
<?php

use Autoframe\\Core\\CliTools\\AfrCliHttpDetect;

if (AfrCliHttpDetect::isCli()) {
    // CLI flow
} elseif (AfrCliHttpDetect::isHttp()) {
    // HTTP flow
}
```

### 3.2 Prompt menu

```php
<?php

use Autoframe\\Core\\CliTools\\AfrCliPromptMenu;

$options = ['Mercedes', 'Audi', 'Porsche'];
$choice = AfrCliPromptMenu::promptMenu('Select your dream car', $options, 'Audi');
```

### 3.3 ANSI text styling

```php
<?php

use Autoframe\\Core\\CliTools\\AfrCliTextColors;

AfrCliTextColors::getInstance()
    ->styleBold(true)
    ->colorGreen('Done')
    ->styleBold(false)
    ->textPrint();
```

### 3.4 Vendor path checks

```php
<?php

use Autoframe\\Core\\CliTools\\AfrVendorDir;

$isVendorPath = AfrVendorDir::pathIsInsideVendorDir(__DIR__);
$vendorPath = AfrVendorDir::getVendorPath();
```

---

## 4) Class-by-Class Documentation Template

> Repeat this section for each class and fill method-level behavior.

### 4.X `<ClassName>`

- **File**: `src/CliTools/<ClassName>.php`
- **Category**: `<detection|parsing|terminal-ui|filesystem|wrapper>`
- **State model**: `<stateless|static-cache|mutable instance>`

#### Methods

| Method | Signature | Returns | Side effects | Notes |
|---|---|---|---|---|
| `<name>` | ``<php signature>`` | `<type>` | `<none/cache/io/output>` | `<constraints/edge cases>` |

#### Behavior notes
- **Input constraints**: `<expected values and types>`
- **Failure model**: `<throws/false/default/fallback>`
- **Environment assumptions**: `<CLI only / HTTP only / cross-context>`
- **Performance/cache**: `<memoized checks / expensive operations>`

#### Example
```php
<?php
// minimal runnable example
```

---

## 5) Operational Deep-Dive (to fill with exact behavior)

### 5.1 `AfrCheckExec`
- Distinguish between:
  - function availability (`is_callable` + `disable_functions` filtering)
  - practical execution success (echo test methods)
- Document cache behavior of boolean checks and when values are reused.

### 5.2 `AfrCliHttpDetect`
- Document CLI detection strategy (`PHP_SAPI`, response code behavior, `STDIN`).
- Document HTTP/HTTPS detection and reverse-proxy/load-balancer forwarded headers.
- Clarify when to pass `AfrRequestClass` vs relying on `$_SERVER`.

### 5.3 `AfrCliPromptMenu`
- Prompt rendering format and default option selection behavior.
- Input validation (invalid index / empty input / interrupt behavior).
- Non-CLI fallback/guard pattern.

### 5.4 `AfrCliTextColors`
- ANSI style toggles and fluent API chaining rules.
- Default reset behavior to avoid style leakage across printed lines.
- Terminal compatibility caveats (Windows shells, redirected output, logs).

### 5.5 `AfrGetOpt`
- Supported option grammar (short, grouped short, long, equals/value formats).
- Behavior for unknown options, missing values, and positional arguments.
- Output data structure and rest-index semantics.

### 5.6 `AfrSysTempDir`
- Resolution precedence (`AFR_SYS_TEMP_DIR`, env vars, `sys_get_temp_dir`, fallbacks).
- Hash/isolation strategy and descriptor file behavior.
- Multi-user/multi-install use cases and implications.

### 5.7 `AfrVendorDir`
- Relation to `AfrVendorPath` wrapper implementation.
- Returned path semantics for vendor/base/composer metadata.

---

## 6) Integration Patterns

### 6.1 Bootstrap context guard
- Detect CLI/HTTP early and split flow to avoid mixed runtime assumptions.

### 6.2 CLI command entrypoint stack
- `AfrCheckExec` -> `AfrGetOpt` -> `AfrCliPromptMenu` -> `AfrCliTextColors` for robust command UX.

### 6.3 Temp + vendor aware tooling
- Combine `AfrSysTempDir` and `AfrVendorDir` for stable cache/artifact locations tied to installation context.

---

## 7) Testing Matrix (recommended)

| Concern | Suggested tests | Cases |
|---|---|---|
| CLI/HTTP detection | `tests/CliTools/AfrCliHttpDetect*` | CLI, HTTP native, HTTPS forwarded, proxy headers |
| Exec capabilities | `tests/CliTools/AfrCheckExec*` | disabled functions, callable checks, echo tests |
| Prompt behavior | `tests/CliTools/AfrCliPromptMenu*` | default select, invalid input, non-CLI guard |
| ANSI output builder | `tests/CliTools/AfrCliTextColors*` | chaining, reset, style toggles |
| Arg parsing | `tests/CliTools/AfrGetOpt*` | short/long flags, values, unknown args, rest index |
| Temp dir resolution | `tests/CliTools/AfrSysTempDir*` | env override, fallback path, writable checks |
| Vendor helpers | `tests/CliTools/AfrVendorDir*` | inside/outside vendor path, composer metadata |

---

## 8) Contribution Checklist

Before updating `src/CliTools/*`:

- [ ] Method table updated for each touched class
- [ ] At least one runnable example added/updated
- [ ] Environment-specific caveats documented
- [ ] Testing matrix synced with behavior changes
- [ ] AI-friendly YAML index updated if class list changes

---

## 9) Writing Rules (AI + Human maintainers)

- Keep section numbering stable for downstream tooling.
- Keep one behavioral rule per bullet.
- Prefer explicit method contracts over descriptive prose.
- Mark context sensitivity clearly (`CLI only`, `HTTP only`, `cross-context`).
- If behavior depends on headers/env vars, list exact key names.
