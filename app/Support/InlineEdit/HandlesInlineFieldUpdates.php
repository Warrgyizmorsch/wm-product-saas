<?php

namespace App\Support\InlineEdit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Shared request-handling logic for single-field inline updates. Each
 * consuming controller defines its own route/action (so implicit route-model
 * binding keeps working) and its own inlineFieldSchema() allowlist mapping
 * field name to validation rules and a handler closure that performs the
 * actual update via that module's own Service/Repository.
 */
trait HandlesInlineFieldUpdates
{
    /**
     * @return array<string, array{rules: array, handler: \Closure}>
     */
    abstract protected function inlineFieldSchema(): array;

    protected function handleInlineFieldUpdate(Request $request, Model $model): JsonResponse
    {
        $this->authorize('update', $model);

        $field = $request->validate(['field' => ['required', 'string']])['field'];

        $schema = $this->inlineFieldSchema()[$field] ?? null;

        if ($schema === null) {
            throw ValidationException::withMessages([
                'field' => "Field '{$field}' is not inline-editable.",
            ]);
        }

        $validated = Validator::make(
            ['value' => $request->input('value')],
            ['value' => $schema['rules']],
        )->validate();

        $value = ($schema['handler'])($model, $validated['value']);

        return response()->json([
            'ok'    => true,
            'field' => $field,
            'value' => $value,
        ]);
    }
}
