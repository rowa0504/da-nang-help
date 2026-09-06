<?php

// Chỉ dịch các rule thực sự đang được dùng (đã kiểm tra toàn bộ rules()
// trong app/Http/Requests). Xem ghi chú trong lang/en/validation.php.

return [
    'required' => 'Trường :attribute là bắt buộc.',
    'string' => 'Trường :attribute phải là chuỗi ký tự.',
    'numeric' => 'Trường :attribute phải là số.',
    'integer' => 'Trường :attribute phải là số nguyên.',
    'email' => 'Trường :attribute phải là địa chỉ email hợp lệ.',
    'lowercase' => 'Trường :attribute phải viết thường.',
    'unique' => ':attribute đã được sử dụng.',
    'confirmed' => 'Xác nhận :attribute không khớp.',
    'array' => 'Trường :attribute phải là một mảng.',
    'distinct' => 'Trường :attribute có giá trị trùng lặp.',
    'exists' => ':attribute đã chọn không hợp lệ.',
    'in' => ':attribute đã chọn không hợp lệ.',
    'decimal' => 'Trường :attribute phải có :decimal chữ số thập phân.',
    'regex' => 'Định dạng trường :attribute không hợp lệ.',
    'date' => 'Trường :attribute phải là ngày giờ hợp lệ.',
    'file' => 'Trường :attribute phải là một tệp.',

    'between' => [
        'numeric' => 'Trường :attribute phải nằm trong khoảng :min đến :max.',
    ],
    'min' => [
        'numeric' => 'Trường :attribute phải tối thiểu là :min.',
        'string' => 'Trường :attribute phải có ít nhất :min ký tự.',
        'array' => 'Trường :attribute phải có ít nhất :min mục.',
        'file' => 'Trường :attribute phải tối thiểu :min kilobyte.',
    ],
    'max' => [
        'numeric' => 'Trường :attribute không được lớn hơn :max.',
        'string' => 'Trường :attribute không được vượt quá :max ký tự.',
        'array' => 'Trường :attribute không được có quá :max mục.',
        'file' => 'Trường :attribute không được vượt quá :max kilobyte.',
    ],
    'password' => [
        'min' => 'Trường :attribute phải có ít nhất :min ký tự.',
    ],
];
