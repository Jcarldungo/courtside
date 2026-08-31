import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AdminLayout>
            <Head title="Your account" />

            <h1 className="font-display text-2xl font-bold text-ink">Your account</h1>

            <div className="mt-6 max-w-xl space-y-6">
                <div className="rounded-2xl border border-ink/10 bg-white p-6">
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </div>

                <div className="rounded-2xl border border-ink/10 bg-white p-6">
                    <UpdatePasswordForm />
                </div>

                <div className="rounded-2xl border border-ink/10 bg-white p-6">
                    <DeleteUserForm />
                </div>
            </div>
        </AdminLayout>
    );
}
