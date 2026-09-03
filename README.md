# Claude Code CLI for PHP

A small, resilient PHP SDK for controlling the Claude Code CLI. The package follows Claude Code's native model:

```text
Claude → Session → Run → Messages / Events → Result
```

It uses one `claude -p --output-format stream-json --verbose` process per query. Follow-up queries resume the session by ID; no persistent child process is required.

## Requirements

- PHP 8.2 or newer
- Claude Code installed and authenticated
- `claude` available on `PATH`

```bash
composer require develate/claudecode-cli-php
```

## Query a session

```php
use Develate\ClaudecodeCli\Claude;
use Develate\ClaudecodeCli\Value\PermissionMode;

$claude = new Claude();

$session = $claude->session(
    cwd: '/var/www/app',
    model: 'sonnet',
    permissionMode: PermissionMode::AcceptEdits,
    tools: ['Read', 'Edit', 'Bash'],
    allowedTools: ['Read', 'Edit', 'Bash(composer *)'],
    disallowedTools: ['Bash(rm *)'],
);

$result = $session->query(
    prompt: 'Fix the failing tests.',
    maxTurns: 10,
    maxBudgetUsd: 2.00,
);

echo $result->text;
echo $result->usage->inputTokens;
echo $result->usage->outputTokens;
echo $result->estimatedCostUsd;
```

After the first query, `$session->id()` contains Claude's session ID. Later queries automatically use `--resume`:

```php
$session->query('Now refactor the implementation.');
```

Resume or branch explicitly:

```php
$continued = $claude->resume($sessionId);
$continued->query('Continue.');

$branch = $continued->fork();
$branch->query('Try a completely different implementation.');
```

A fork has no ID until Claude creates its new session. The original session remains unchanged.

## Streaming

Streaming is the primitive; `query()` creates a `Run` and drains it.

```php
use Develate\ClaudecodeCli\Event\TextDelta;
use Develate\ClaudecodeCli\Tool\ToolUse;

$run = $session->stream('Explain this repository.');

foreach ($run as $item) {
    if ($item instanceof TextDelta) {
        echo $item->text;
    }

    if ($item instanceof ToolUse && $item->name === 'Bash') {
        echo '$ '.($item->input['command'] ?? '').PHP_EOL;
    }
}

$result = $run->result();
```

A run yields `Message`, `Event`, `ToolUse`, and `ToolResult` objects. Messages and events are deliberately separate concepts. `--include-partial-messages` is always enabled, so API text deltas become `TextDelta` events when Claude emits them.

Calling `$run->cancel()` asks the running child process to stop. A cancelled run throws `ProcessCancelled`.

## Messages and content blocks

Known messages are represented as `SystemMessage`, `AssistantMessage`, `UserMessage`, and `ResultMessage`. Assistant content remains block-based:

```php
foreach ($result->messages() as $message) {
    if ($message instanceof AssistantMessage) {
        echo $message->text();

        foreach ($message->toolUses() as $toolUseBlock) {
            // Native tool-use content block.
        }
    }
}
```

The V1 content types are `TextBlock`, `ThinkingBlock`, `ToolUseBlock`, and `UnknownBlock`.

Every protocol object exposes its complete source envelope or block through `raw()`. New top-level types and content blocks become `UnknownMessage`, `UnknownEvent`, or `UnknownBlock`; extra fields never cause parsing to fail. Only malformed JSON or a broken process is exceptional.

## Result views

`ToolUse` is the protocol-level source of truth. Commands and file changes are convenience views:

```php
foreach ($result->toolUses() as $toolUse) {
    // Every native tool invocation.
}

foreach ($result->commands() as $command) {
    printf("$ %s\n%s\n", $command->command, $command->output ?? '');
}

foreach ($result->fileChanges() as $change) {
    echo $change->kind->value.': '.$change->path.PHP_EOL;
}

foreach ($result->thinking() as $thinking) {
    echo $thinking->thinking;
}
```

Only `Edit` and `Write` tool calls become file changes. V1 does not guess whether a `Write` added or replaced a file.

Other result data includes:

- aggregate `Usage` and per-model `ModelUsage`
- estimated cost, intentionally named `estimatedCostUsd`
- structured output and permission denials
- stop reason, terminal reason, and a normalized `ResultStatus`
- rate-limit information when the CLI emits it
- requested model, reported models, Claude version, and working directory
- main-agent and subagent message views based on `parent_tool_use_id`

Expected terminal results such as max-turns and max-budget are returned as normal `Result` objects. A non-zero process exit without a terminal result message throws `ProcessFailed`.

## Structured output and images

```php
$result = $session->query(
    prompt: 'Review this implementation.',
    schema: [
        'type' => 'object',
        'properties' => [
            'approved' => ['type' => 'boolean'],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['approved', 'issues'],
    ],
    images: ['/tmp/reference.png'],
);

$review = $result->structuredOutput;
```

The schema maps directly to `--json-schema`. In the V1 text-input transport, image paths are appended as explicit file references so Claude can inspect them with its file tools. The public `images` argument is transport-neutral and can later map to native multimodal blocks without changing session APIs.

## Configuration

```php
$claude->version();

$projectClaude = $claude->in('/var/www/app');

$unsafeClaude = $claude->dangerouslyBypassPermissions();
```

Bypass mode is deliberately named as dangerous. Tool availability (`tools`), pre-approval (`allowedTools`), and denial (`disallowedTools`) remain separate.

## Exceptions

- `ClaudeNotFound`
- `ProcessFailed`
- `ProcessTimedOut`
- `ProcessCancelled`
- `InvalidStreamJson`
- `UnsupportedFeature`

All extend `ClaudeException`.

## V1 boundary

V1 intentionally does not include a persistent bidirectional process, interrupts with protocol acknowledgements, runtime model or permission changes, interactive permission callbacks, MCP runtime control, hooks, plugin management, quota APIs, or a pricing database.

## Tests

```bash
composer test
```
