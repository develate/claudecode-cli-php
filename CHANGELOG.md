# Changelog

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
