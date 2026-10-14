import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { LegalDocumentView } from '@/Components/LegalDocumentView';
import { useTranslation } from '@/hooks/useTranslation';
import { SupportedLocale } from '@/types';
import legalEn from '@/legal/en';
import legalJa from '@/legal/ja';
import legalVi from '@/legal/vi';

const CONTENT = { en: legalEn, ja: legalJa, vi: legalVi };

interface Props {
    operatorName: string | null;
    contactEmail: string | null;
    effectiveDate: string | null;
    governingLocale: SupportedLocale;
}

export default function Privacy({ operatorName, contactEmail, effectiveDate, governingLocale }: Props) {
    const { locale, t } = useTranslation();
    const document = CONTENT[locale].privacy;

    return (
        <AppLayout>
            <Head title={t('nav.privacy')} />
            <LegalDocumentView
                document={document}
                operatorName={operatorName}
                contactEmail={contactEmail}
                effectiveDate={effectiveDate}
                governingLocale={governingLocale}
            />
        </AppLayout>
    );
}
