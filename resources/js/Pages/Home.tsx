import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, ClipboardList, Languages, ShieldCheck, Star, Users } from 'lucide-react';
import { CategoryOption, SharedProps } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { buttonClasses } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { HeroIllustration } from '@/Components/HeroIllustration';
import { CategoryIcon } from '@/Components/CategoryIcon';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    categories: CategoryOption[];
}

const STEP_ITEMS: { titleKey: TranslationKey; descriptionKey: TranslationKey; Icon: typeof ClipboardList }[] = [
    { titleKey: 'home.steps.step1.title', descriptionKey: 'home.steps.step1.description', Icon: ClipboardList },
    { titleKey: 'home.steps.step2.title', descriptionKey: 'home.steps.step2.description', Icon: Users },
    { titleKey: 'home.steps.step3.title', descriptionKey: 'home.steps.step3.description', Icon: Star },
];

const TRUST_ITEMS: { key: TranslationKey; Icon: typeof ShieldCheck }[] = [
    { key: 'home.trust_section.admin_review', Icon: ShieldCheck },
    { key: 'home.trust_section.reviews', Icon: Star },
    { key: 'home.trust_section.multilingual', Icon: Languages },
    { key: 'home.trust_section.progress', Icon: Activity },
];

export default function Home({ categories }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t('app.name')} />

            <section data-testid="hero" className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                <div className="grid items-center gap-10 md:grid-cols-2">
                    <div>
                        <span className="text-sm font-semibold uppercase tracking-wide text-brand-600">{t('home.hero.eyebrow')}</span>
                        <h1 className="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl">{t('home.hero.heading')}</h1>
                        <p className="mt-4 text-gray-600">{t('home.hero.description')}</p>

                        {auth.user ? (
                            <Card className="mt-6">
                                <p className="text-gray-800">{t('home.welcome_back', { name: auth.user.name })}</p>
                                <Link href="/dashboard" className={`mt-3 ${buttonClasses('primary', 'default')}`}>
                                    {t('home.go_to_dashboard')}
                                </Link>
                            </Card>
                        ) : (
                            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                                <Link href="/register" className={buttonClasses('primary', 'default', 'w-full sm:w-auto')}>
                                    {t('home.hero.cta_primary')}
                                </Link>
                                <Link href="/register?role=provider" className={buttonClasses('secondary', 'default', 'w-full sm:w-auto')}>
                                    {t('home.hero.cta_secondary')}
                                </Link>
                            </div>
                        )}

                        <p className="mt-4 flex items-center gap-2 text-sm text-gray-600">
                            <ShieldCheck aria-hidden="true" className="h-4 w-4 shrink-0 text-brand-600" />
                            {t('home.hero.trust')}
                        </p>
                    </div>

                    <HeroIllustration className="mx-auto w-full max-w-md" />
                </div>
            </section>

            <section id="how-it-works" className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <h2 className="text-center text-2xl font-bold text-slate-900">{t('home.steps.heading')}</h2>
                <div className="mt-8 grid gap-6 sm:grid-cols-3">
                    {STEP_ITEMS.map((step, index) => (
                        <Card key={step.titleKey}>
                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">
                                {index + 1}
                            </div>
                            <h3 className="mt-3 flex items-center gap-2 font-semibold text-slate-900">
                                <step.Icon aria-hidden="true" className="h-4 w-4 text-brand-600" />
                                {t(step.titleKey)}
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">{t(step.descriptionKey)}</p>
                        </Card>
                    ))}
                </div>
            </section>

            {categories.length > 0 && (
                <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                    <h2 className="text-center text-2xl font-bold text-slate-900">{t('home.categories.heading')}</h2>
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {categories.map((category) => (
                            <Card key={category.id} className="flex items-center gap-3">
                                <CategoryIcon slug={category.slug} className="h-6 w-6 shrink-0 text-brand-600" />
                                <span className="font-medium text-slate-900">{category.name}</span>
                            </Card>
                        ))}
                    </div>
                </section>
            )}

            <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <h2 className="text-center text-2xl font-bold text-slate-900">{t('home.trust_section.heading')}</h2>
                <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {TRUST_ITEMS.map((item) => (
                        <div key={item.key} className="flex flex-col items-center gap-2 text-center">
                            <item.Icon aria-hidden="true" className="h-6 w-6 text-brand-600" />
                            <p className="text-sm text-gray-700">{t(item.key)}</p>
                        </div>
                    ))}
                </div>
            </section>
        </AppLayout>
    );
}
