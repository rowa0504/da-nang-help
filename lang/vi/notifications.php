<?php

return [
    'greeting' => 'Chào :name,',

    'offer_received' => [
        'subject' => 'Có báo giá mới cho ":title"',
        'body' => 'Một Provider đã gửi báo giá :currency :price cho yêu cầu ":title" của bạn.',
    ],
    'offer_accepted' => [
        'subject' => 'Báo giá của bạn cho ":title" đã được chấp nhận',
        'body' => 'Customer đã chấp nhận báo giá của bạn cho ":title". Bạn có thể xem thông tin liên hệ trên trang Job.',
    ],
    'job_completion_reported' => [
        'subject' => 'Provider đã báo hoàn thành cho ":title"',
        'body' => 'Provider đã báo cáo công việc ":title" đã hoàn thành. Vui lòng xác nhận khi sẵn sàng.',
    ],
    'job_completed' => [
        'subject' => 'Công việc ":title" đã hoàn thành',
        'body' => 'Công việc ":title" (giá thỏa thuận: :currency :price) đã được xác nhận hoàn thành.',
    ],
    'review_posted' => [
        'subject' => 'Bạn nhận được đánh giá mới',
        'body' => 'Customer đã để lại đánh giá :rating sao cho ":title".',
    ],
];
