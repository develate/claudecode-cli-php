# Changelog

## v1.3.1 — 2026-10-27

- Always send `--permission-mode` on start, resume and fork, including `--permission-mode default` for `PermissionMode::Default`. Omitting the flag could silently select auto mode depending on the CLI version, provider and telemetry settings.

## 2026-10-11

- `RunOptions::$maxBackgroundWait` ends the run again once the limit is spent. Since Claude Code 2.1.292 a closed stdin no longer stops background commands, so the limit had no effect: the process waited for the commands and answered them however long they ran. The process is now stopped at the limit (SIGTERM, which ends the commands too), and the run keeps the results it already has. The clock stops once the commands have finished and the model is answering them.

## 2026-10-03

- Added steering: `Run::steer()` writes a user message into the running process, and `Run::onTick()` runs between reads so input from elsewhere can be delivered while the model thinks. Claude Code hands the message to the model at its next tool call, or answers it as a follow-up in the same run. Runs now pass `--replay-user-messages`; the replayed `UserMessage` (`isReplay`, `uuid`) confirms receipt, and stdin stays open until every steering message has been confirmed and answered.

- Added banked-reset availability and redemption to `LimitResetClient`, with typed grants, status and outcomes for Claude Code's `cedar_ember` program. Requests use the CLI surface, redemption accepts a stable request ID, and per-use credit IDs distinguish multiple resets in one grant.

## Unreleased

### Changed

- The prompt is sent as a stream-json user message (`--input-format
  stream-json`), and stdin stays open while background tasks the model started
  are still running. Claude Code stops those tasks once stdin closes after a
  result; left open, it reports each finished task to the model, which answers
  again in the same run. Stdin closes after the first result that leaves
  nothing running.
- A run's `Result` adds up `usage`, `numTurns` and `permissionDenials` over
  every result the run produced. Cost and per-model usage were already totals.

### Added

- `RunOptions::$maxBackgroundWait`: how many seconds a result may wait for the
  background tasks it left running. Null waits until the run times out.

- `LimitResetClient`, the headless equivalent of Claude Code's interactive
  `/limit-reset` command: `status()` reads the `juniper_tide` availability
  block (`GET /api/oauth/usage?at_wall=1&skip_spend=1`) and `reset()` claims
  the weekly session-limit reset
  (`POST /api/organizations/{uuid}/reset_rate_limits`). No new dependencies:
  the default sender is proxy-aware plain PHP streams, overridable with a
  `$http` callable.
- `LimitResetStatus` (with `redeemable()`), `LimitResetResult` (with
  `didReset()` and `spent()`), the `LimitResetOutcome`,
  `LimitResetIneligibility` and `LimitResetArm` enums, and the
  `LimitResetFailed` exception (with `isAuthenticationError()`).

## v1.1.0

The final v1 contract. `SessionOptions` and `RunOptions` replace the long
parameter lists of `Claude::session()`, `Claude::resume()`, `Session::query()`
and `Session::stream()`; the option objects are the only breaking change.

### Added

- `SessionOptions` bundles cwd, model, effort, permission mode, tools, setting
  sources, settings, MCP configuration, system prompt and streaming options.
  `tools`, `settingSources` and `chrome` distinguish "unset" from "empty",
  because Claude Code treats those cases differently.
- `RunOptions` bundles limits, schema, images and timeout.
- A process environment on `Claude`, applied identically to `--version`,
  `auth status` and every run. A `false` value removes an inherited variable.
- `Claude::authStatus()` and the `AuthStatus` value, read from
  `claude auth status --json`. A non-zero exit that still produced JSON is
  parsed rather than thrown: "not signed in" is an answer.
- The `Effort` enum and `--effort`.
- `--setting-sources` (including the empty list), `--settings`, `--mcp-config`,
  `--strict-mcp-config`, `--permission-prompt-tool`, `--permission-prompts`,
  `--append-system-prompt`, `--system-prompt`, `--no-chrome`/`--chrome`,
  `--disable-slash-commands` and `--forward-subagent-text`.
- `ThinkingDelta`, a separate event type from `TextDelta`, so a host cannot
  stream the model's reasoning into the same view as its answer.
- `SessionInit` and `Result::init()`, the `system`/`init` metadata: the resolved
  model, the tools that survived the flags, the MCP servers that connected, the
  credential source and the fast-mode state.
- `Session::with()`, which keeps the conversation and changes only the options
  the next run is told about.
- `SettingSource` and `PermissionPrompts` enums.

### Changed

- The prompt travels on the child process' stdin instead of as a trailing
  argument. Several Claude Code flags are variadic and swallowed a positional
  prompt; stdin also removes the argument-length ceiling a long transcript hits.
- `dangerouslyBypassPermissions()` no longer overrides a permission mode the
  caller set explicitly.
- `--include-partial-messages` is still on by default but can be turned off.

## v1.0.0

Initial release.
