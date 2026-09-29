<?php

return [
    'greeting' => ':name 様',

    'offer_received' => [
        'subject' => '「:title」に新しいオファーが届きました',
        'body' => 'Providerから「:title」への依頼に対し、:currency :price のオファーが届きました。',
    ],
    'offer_accepted' => [
        'subject' => '「:title」へのオファーが承諾されました',
        'body' => '依頼者が「:title」へのあなたのオファーを承諾しました。連絡先は作業ページで確認できます。',
    ],
    'job_completion_reported' => [
        'subject' => '「:title」の完了報告がありました',
        'body' => 'Providerが「:title」の作業完了を報告しました。ご確認をお願いします。',
    ],
    'job_completed' => [
        'subject' => '「:title」の作業が完了しました',
        'body' => '「:title」の作業（合意価格: :currency :price）が完了として確定しました。',
    ],
    'review_posted' => [
        'subject' => '新しいレビューが届きました',
        'body' => 'Customerから「:title」に対し、星:rating のレビューが投稿されました。',
    ],
];
