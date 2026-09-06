<?php

return [
    'greeting' => ':name 様',

    'offer_received' => [
        'subject' => '「:title」に新しいオファーが届きました',
        'body' => 'Providerから「:title」への依頼に対し、:currency :price のオファーが届きました。',
    ],
    'offer_accepted' => [
        'subject' => '「:title」へのオファーが承諾されました',
        'body' => 'Customerが「:title」へのあなたのオファーを承諾しました。連絡先はJobページで確認できます。',
    ],
    'job_completion_reported' => [
        'subject' => '「:title」の完了報告がありました',
        'body' => 'Providerが「:title」の作業完了を報告しました。ご確認をお願いします。',
    ],
    'job_completed' => [
        'subject' => '「:title」のJobが完了しました',
        'body' => '「:title」のJob（合意価格: :currency :price）が完了として確定しました。',
    ],
    'review_posted' => [
        'subject' => '新しいレビューが届きました',
        'body' => 'Customerから「:title」に対し、星:rating のレビューが投稿されました。',
    ],
];
