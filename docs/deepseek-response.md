# Reading the DeepSeek API Response

DeepSeek's chat completions API is OpenAI-compatible. This is how to read what
`runModel()` returns (`app/Console/Commands/chat.php`).

## Response shape

```json
{
  "id": "chat-...",
  "model": "deepseek-chat",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "Hello, how can I help?",
        "reasoning_content": "...",
        "tool_calls": [
          {
            "id": "call_abc123",
            "type": "function",
            "function": {
              "name": "read_file",
              "arguments": "{\"file\":\"package.json\"}"
            }
          }
        ]
      },
      "finish_reason": "tool_calls"
    }
  ],
  "usage": { "prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0 }
}
```

Key point: the reply is **not** at the top level. It lives at
`choices[].message`. The `choice` wrapper only holds `index`, `message`, and
`finish_reason`.

## What to extract

| You want | Path |
| --- | --- |
| Assistant reply object | `choices[0]['message']` |
| Text answer | `choices[0]['message']['content']` |
| Whether model wants a tool | `choices[0]['message']['tool_calls']` (absent if none) |
| Why it stopped | `choices[0]['finish_reason']` (`stop`, `tool_calls`, `length`) |
| Token cost | `usage` |

## Building conversation history

Append the **whole `message` object** to history, not the `choice` wrapper.
Each item in the `messages` array you send back must carry a top-level `role`.
`choice` has no `role` (it sits one level down inside `message`), so pushing the
wrapper triggers `missing field 'role'` on the next request.

```php
$assistantMessage = collect($choice['message'])->except('reasoning_content');
```

Strip `reasoning_content` before storing — it is model scratch output, not part
of the conversation, and DeepSeek rejects it on later requests.

## Handling tool calls

`tool_calls[]` is only present when the model wants a tool. Each entry:

- `id` — pass back as `tool_call_id` on your result message.
- `function.name` — which tool to run.
- `function.arguments` — a **JSON string**, not an array. Decode it:
  `json_decode($toolCall['function']['arguments'])->file`.

Note `map()` returns `null` for choices with no `tool_calls`. Filter those out
(`->filter()`) or `empty()` sees `[null]` as non-empty.

After running the tool, append a result message and call the API again:

```php
[
    'role' => 'tool',
    'tool_call_id' => $toolCall['tool_call_id'],
    'content' => $result,
]
```

Loop: keep calling `runModel()` while `tool_calls` come back. Stop when
`finish_reason` is `stop` (no tool_calls) — that message's `content` is the final
answer.

## Managing history growth

The tool result message must stay in history for the **immediately following**
request — the API requires every assistant `tool_calls` to be answered by a
matching `tool` result, or it returns 400. You cannot skip it while the loop is
running.

Long term it is a cost problem, not a correctness one. A read file's contents
get re-sent on every later request, growing the payload and eventually blowing
the context limit. The token cost is what you send each turn, not what you keep
in PHP memory.

Pairing trap: you cannot drop just the tool result. The assistant `tool_call`
message and its `tool` result are a pair. Remove the result but keep the
`tool_call` and the next request 400s on a dangling call. Remove **both together
or neither**.

Once the final answer is produced, compact:

| Strategy | What it does |
| --- | --- |
| Keep all | Simplest. History and cost grow with every file read. |
| Compact pair | Replace the tool `content` with a short placeholder (e.g. `[read package.json, 1.2kb]`). Keeps pairing valid, drops the bulk. |
| Drop pair | Remove the assistant `tool_call` message and its `tool` result together. Model keeps its own summary but loses the raw bytes. |

Compacting is usually best: the model already used the content to answer and
rarely needs the full bytes again.
