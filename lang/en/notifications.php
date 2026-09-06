<?php

return [
    'greeting' => 'Hi :name,',

    'offer_received' => [
        'subject' => 'New offer received for ":title"',
        'body' => 'A Provider has sent you an offer of :currency :price for your request ":title".',
    ],
    'offer_accepted' => [
        'subject' => 'Your offer for ":title" was accepted',
        'body' => 'The Customer has accepted your offer for ":title". You can now see the contact details on the Job page.',
    ],
    'job_completion_reported' => [
        'subject' => 'Provider reported completion for ":title"',
        'body' => 'The Provider has reported that the work for ":title" is complete. Please confirm when you are ready.',
    ],
    'job_completed' => [
        'subject' => 'Job completed for ":title"',
        'body' => 'The job for ":title" (agreed price: :currency :price) has been confirmed as complete.',
    ],
    'review_posted' => [
        'subject' => 'You received a new review',
        'body' => 'A Customer left you a :rating-star review for ":title".',
    ],
];
