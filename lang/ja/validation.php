<?php

// 実使用ルールのみ翻訳（app/Http/Requests配下の全rules()を確認済み）。
// lang/en/validation.php の注記を参照。

return [
    'required' => ':attribute は必須項目です。',
    'string' => ':attribute は文字列で入力してください。',
    'numeric' => ':attribute は数値で入力してください。',
    'integer' => ':attribute は整数で入力してください。',
    'boolean' => ':attribute はtrueまたはfalseを指定してください。',
    'email' => ':attribute には有効なメールアドレスを入力してください。',
    'lowercase' => ':attribute は小文字で入力してください。',
    'unique' => ':attribute は既に使用されています。',
    'confirmed' => ':attribute の確認が一致しません。',
    'array' => ':attribute は配列で指定してください。',
    'distinct' => ':attribute に重複した値があります。',
    'exists' => '選択された :attribute は無効です。',
    'in' => '選択された :attribute は無効です。',
    'not_in' => '選択された :attribute は無効です。',
    'decimal' => ':attribute は小数点以下 :decimal 桁で入力してください。',
    'regex' => ':attribute の形式が正しくありません。',
    'date' => ':attribute には有効な日時を入力してください。',
    'file' => ':attribute はファイルを指定してください。',
    'phone_number' => ':attribute の形式が正しくありません。数字・半角スペース・-・()・先頭の+のみ使用できます。',
    'photo_limit_exceeded' => '依頼に登録できる写真は最大:max枚までです。',
    'prohibited' => ':attribute は入力できません。',

    'between' => [
        'numeric' => ':attribute は :min から :max の間で入力してください。',
    ],
    'min' => [
        'numeric' => ':attribute は :min 以上で入力してください。',
        'string' => ':attribute は :min 文字以上で入力してください。',
        'array' => ':attribute は :min 個以上選択してください。',
        'file' => ':attribute は :min キロバイト以上にしてください。',
    ],
    'max' => [
        'numeric' => ':attribute は :max 以下で入力してください。',
        'string' => ':attribute は :max 文字以内で入力してください。',
        'array' => ':attribute は :max 個以内で選択してください。',
        'file' => ':attribute は :max キロバイト以内にしてください。',
    ],
    'password' => [
        'min' => ':attribute は :min 文字以上で入力してください。',
    ],

    'attributes' => [
        'other_service_details' => 'その他サービスの詳細',
        'phone' => '電話番号',
        'price' => '価格',
        'currency' => '通貨',
        'message' => 'メッセージ',
        'available_at' => '対応可能日時',
    ],
];
