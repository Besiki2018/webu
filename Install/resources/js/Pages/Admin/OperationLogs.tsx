import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { OperationLogsTable } from '@/components/Admin/OperationLogsTable';
import { useAdminLoading } from '@/hooks/useAdminLoading';
import { TableSkeleton } from '@/components/Admin/skeletons';
import { useTranslation } from '@/contexts/LanguageContext';
import type { PageProps } from '@/types';

export default function OperationLogs({ auth }: PageProps) {
    const { t } = useTranslation();
    const { isLoading } = useAdminLoading();

    if (isLoading) {
        return (
            <AdminLayout user={auth.user!} title={t('Operation Logs')}>
                <AdminPageHeader
                    title={t('Operation Logs')}
                    subtitle={t('Inspect build, publish, payment, and subscription events')}
                />
                <TableSkeleton rows={10} showSearch filterCount={3} />
            </AdminLayout>
        );
    }

    return (
        <AdminLayout user={auth.user!} title={t('Operation Logs')}>
            <AdminPageHeader
                title={t('Operation Logs')}
                subtitle={t('Inspect build, publish, payment, and subscription events')}
            />

            <OperationLogsTable />
        </AdminLayout>
    );
}
