import { LegalContent } from './types';

const content: LegalContent = {
    terms: {
        title: 'Terms of Service (Draft)',
        sections: [
            {
                heading: '1. Scope',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'These Terms of Service ("Terms") govern your use of the Da Nang Help matching platform ("the Service") operated by {{operator_name}} ("the Operator"). At registration, you will be asked to explicitly confirm your agreement to these Terms via a checkbox. Once registration is complete, you are deemed to have agreed to these Terms.',
                    },
                ],
            },
            {
                heading: '2. Description of the Service',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'The Service is a matching platform connecting Customers looking for services in Da Nang ("Customers") with Providers who offer services on the platform ("Providers"). The Operator only provides a place for Customers and Providers to connect and is not a party to any transaction or service actually performed between them. The Service does not provide payment processing or escrow functionality; payment is made directly between the Customer and the Provider. The Operator does not guarantee that any posted price reflects the terms actually agreed between the parties.',
                    },
                ],
            },
            {
                heading: '3. Eligibility',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'The Service is available only to individuals 18 years of age or older. You will be asked to confirm you are 18 or older at registration.',
                    },
                ],
            },
            {
                heading: '4. Prohibited Conduct',
                blocks: [
                    { type: 'p', text: 'You must not, in connection with your use of the Service:' },
                    {
                        type: 'ul',
                        items: [
                            'violate any applicable law, or offer or arrange any unlawful service or goods;',
                            'infringe the rights of others (intellectual property, privacy, reputation, etc.);',
                            'register or post false information;',
                            'place undue load on the Service’s systems, or attempt unauthorized access;',
                            'post any service whose offer or arrangement is restricted under Vietnamese law;',
                            'engage in any other conduct the Operator deems inappropriate.',
                        ],
                    },
                ],
            },
            {
                heading: '5. Your Content and the License You Grant',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'You are solely responsible for the content you post (Service Requests, Offers, Reviews, and other content, "User Content"). By posting User Content, you grant the Operator a free, non-exclusive license to use it to the extent necessary to operate the Service, including display, moderation, and machine translation.',
                    },
                ],
            },
            {
                heading: '6. Moderation',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'The Operator may hide User Content, or revoke or suspend a Provider’s verification, without prior notice, where it violates these Terms or applicable law.',
                    },
                ],
            },
            {
                heading: '7. Machine Translation',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Content on the Service may be displayed in multiple languages using machine translation via Amazon Translate. Machine translation results may not be accurate, and the Operator does not guarantee their accuracy. For anything important, please verify the original text or communicate directly.',
                    },
                ],
            },
            {
                heading: '8. Not an Emergency Service',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'The Service is not intended for emergency response or emergency medical services. In any emergency involving life, health, or property, do not use the Service — contact the appropriate emergency services directly.',
                    },
                ],
            },
            {
                heading: '9. Disclaimers and Limitation of Liability',
                blocks: [
                    { type: 'p', text: 'To the extent permitted by applicable law, the Operator is not liable for:' },
                    {
                        type: 'ul',
                        items: [
                            'the actual service or transaction performed between a Customer and a Provider, including its content, quality, or outcome;',
                            'the accuracy or lawfulness of User Content;',
                            'the accuracy of machine translation;',
                            'any damage arising from interruption or suspension of the Service, or loss of data.',
                        ],
                    },
                    {
                        type: 'p',
                        text:
                            'Where the Operator is found liable notwithstanding the above, such liability is limited to the extent permitted by applicable law.',
                    },
                ],
            },
            {
                heading: '10. Governing Law and Dispute Resolution',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'These Terms are governed by the laws of Vietnam. The specific dispute resolution procedure and venue will be finalized, with legal review, before the Service is made available to the public.',
                    },
                ],
            },
            {
                heading: '11. Changes to These Terms',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Where the Operator amends these Terms, it will announce the changes and their effective date in advance, through the Service or other appropriate means, with reasonable notice. For changes that materially affect users, the Operator will seek renewed consent as required by applicable law.',
                    },
                ],
            },
            {
                heading: '12. Language Priority and Contact',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'These Terms are provided in English, Japanese, and Vietnamese. Where versions differ, the {{governing_locale_language}} version prevails. For questions about these Terms, contact {{contact_email}}.',
                    },
                ],
            },
        ],
    },
    privacy: {
        title: 'Privacy Policy (Draft)',
        sections: [
            {
                heading: '1. Information We Collect',
                blocks: [
                    {
                        type: 'ul',
                        items: [
                            'Account information: name, email address, password (stored as a hash; the Operator cannot see the plain-text password), role, display language, phone number (optional)',
                            'Provider information: business name, bio, categories served, service areas, verification status and history',
                            'Content you submit: Service Request content (title, description, category, area, address, urgency, photos), Offer content (price, message, availability), Job content, Review content (rating, comment)',
                            'Notification information: the type, JSON content, read status, and timestamps of in-app notifications. These notifications may also trigger an email, but we do not keep a separate log of whether that email was actually delivered.',
                            'Access information: IP address, session data, access and error logs',
                            'Record metadata: creation/update timestamps, moderation history',
                        ],
                    },
                ],
            },
            {
                heading: '2. Purpose of Use and Retention',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'We use the information above to operate the Service, for account authentication and management, to prevent misuse, to provide customer support, and to comply with legal obligations. We retain information for as long as necessary for these purposes or as required by applicable law, decided case by case. See Section 7 regarding deletion requests.',
                    },
                ],
            },
            {
                heading: '3. Disclosure to Third Parties / Processors',
                blocks: [
                    {
                        type: 'ul',
                        items: [
                            'Amazon Translate (AWS): used to machine-translate the title/description of Service Requests and the message of Offers. Processing primarily occurs in the Singapore region; however, AWS may process or store content in other regions as part of its operations. AWS may store and use the input content to maintain and improve its services, and to improve and develop its machine learning and AI technologies. Translation accuracy is not guaranteed. Please do not include phone numbers, exact addresses, or other unnecessary personal information in Service Request or Offer text.',
                            'Amazon S3 (AWS): storage of uploaded photos (EXIF stripped, stored privately, served via time-limited signed URLs)',
                            'Amazon RDS (AWS): database storage',
                            'Amazon ECS/Fargate (AWS): application hosting',
                            'Amazon SES (AWS): sending notification emails',
                            'Amazon CloudWatch (AWS): collection of application and error logs',
                        ],
                    },
                    {
                        type: 'p',
                        text:
                            'We do not sell personal data. We may disclose personal data to the extent necessary in response to a lawful request.',
                    },
                ],
            },
            {
                heading: '4. Disclosure Among Users',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'A Service Request that is open and visible can be viewed by every approved Provider; however, private fields such as the exact address and phone number are not shown until a Job is formed. How closely a Provider’s categories/areas match the request only affects its ranking within the feed and whether it appears under the "recommended" filter — it does not restrict whether the Provider can view the request or submit an Offer on it. When a Provider submits an Offer, that Offer’s content (price, message) and that Provider’s business name, average rating, and completed-jobs count (and, where applicable, their "other" category details) are shown to the Customer who posted the request. A Provider’s own bio and the full list of their registered categories/areas are visible only to that Provider and to Admins. Review content (rating and comment) is visible to the Customer and Provider who are parties to that Job, and to Admins. The exact address and phone number for a Service Request are not disclosed until a Job is formed (an Offer is accepted); afterward, they are disclosed between the matched Customer and Provider only. Admins may view this information for verification and moderation purposes.',
                    },
                ],
            },
            {
                heading: '5. Cross-Border Transfer',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'The Service’s infrastructure runs on AWS in the Singapore region. Personal data collected in Vietnam is therefore processed and stored outside Vietnam, which constitutes a "cross-border transfer" under Vietnam’s Law on Personal Data Protection (Law No. 91/2025/QH15) and Decree No. 356/2025/ND-CP. We will comply with the procedures required by applicable law for such transfers.',
                    },
                ],
            },
            {
                heading: '6. Cookies',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'We use only the essential (session) cookies required to operate the Service, such as maintaining your login state. We do not use advertising or tracking cookies.',
                    },
                ],
            },
            {
                heading: '7. Your Rights',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Subject to applicable law, you may request access to, correction of, deletion of, or restriction of use of your personal data, or withdraw your consent. The Service does not currently offer a self-service account deletion feature; please contact us using the details in Section 10 to make such a request. We may be unable to fully comply with a request where retention is required by law, to prevent fraud, or to resolve disputes.',
                    },
                ],
            },
            {
                heading: '8. Minors',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'The Service is not intended for use by individuals under 18. We ask you to confirm you are 18 or older at registration.',
                    },
                ],
            },
            {
                heading: '9. Changes to This Policy',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Where the Operator amends this Policy, it will announce the changes and their effective date in advance, through the Service or other appropriate means, with reasonable notice. For changes that materially affect users, the Operator will seek renewed consent as required by applicable law.',
                    },
                ],
            },
            {
                heading: '10. Language Priority and Contact',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'This Policy is provided in English, Japanese, and Vietnamese. Where versions differ, the {{governing_locale_language}} version prevails. For questions about this Policy, contact {{contact_email}}.',
                    },
                ],
            },
        ],
    },
};

export default content;
