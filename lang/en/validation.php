<?php

// Only the rule keys actually used by this app's Form Requests are
// translated here (verified by grepping every rules() method) — this is
// NOT a full copy of Laravel's default validation.php. Any rule not listed
// here is not used anywhere in the app; if one is added later without
// adding its message here, Laravel will fall back to the raw translation
// key instead of a real English sentence, so keep this file in sync with
// actual Form Request usage.

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be a string.',
    'numeric' => 'The :attribute field must be a number.',
    'integer' => 'The :attribute field must be an integer.',
    'boolean' => 'The :attribute field must be true or false.',
    'email' => 'The :attribute field must be a valid email address.',
    'lowercase' => 'The :attribute field must be lowercase.',
    'unique' => 'The :attribute has already been taken.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'array' => 'The :attribute field must be an array.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'exists' => 'The selected :attribute is invalid.',
    'in' => 'The selected :attribute is invalid.',
    'not_in' => 'The selected :attribute is invalid.',
    'decimal' => 'The :attribute field must have :decimal decimal places.',
    'regex' => 'The :attribute field format is invalid.',
    'date' => 'The :attribute field must be a valid date.',
    'file' => 'The :attribute field must be a file.',
    'phone_number' => 'The :attribute field must be a valid phone number (digits, spaces, -, (), and an optional leading + only).',
    'photo_limit_exceeded' => 'A service request may have at most :max photos.',
    'max_utf8_bytes' => 'The :attribute field must not exceed :max bytes when encoded as UTF-8.',
    'prohibited' => 'The :attribute field is prohibited.',

    'between' => [
        'numeric' => 'The :attribute field must be between :min and :max.',
    ],
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
        'array' => 'The :attribute field must have at least :min items.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
    ],
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
    ],
    'password' => [
        'min' => 'The :attribute field must be at least :min characters.',
    ],

    'attributes' => [
        'other_service_details' => 'other service details',
        'phone' => 'phone number',
        'price' => 'price',
        'currency' => 'currency',
        'message' => 'message',
        'available_at' => 'available date and time',
    ],
];
