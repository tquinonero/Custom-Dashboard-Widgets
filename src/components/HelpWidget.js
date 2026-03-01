import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function HelpWidget() {
    const settings = useSelect((select) => select('cdw/store').getSettings());
    const isLoadingSettings = useSelect((select) => select('cdw/store').isLoading('settings'));
    const { fetchSettings } = useDispatch('cdw/store');

    useEffect(() => {
        fetchSettings();
    }, []);

    const adminUrl = window.cdwData?.adminUrl || '';
    const email = settings?.email || '';
    const docsUrl = settings?.docs_url || '';

    return (
        <div className="cdw-help-widget">
            {email && (
                <p>
                    Need help? Contact support at{' '}
                    <a href={`mailto:${email}`}>{email}</a>
                </p>
            )}
            {docsUrl && (
                <p>
                    Visit our <a href={docsUrl}>documentation</a> for more information.
                </p>
            )}
            {!isLoadingSettings && !email && !docsUrl && (
                <p>
                    No support information configured.{' '}
                    <a href={adminUrl + 'options-general.php?page=cdw-settings'}>Configure settings</a>
                </p>
            )}
            <p>
                <a href={adminUrl + 'options-general.php?page=cdw-settings'} className="button">
                    Edit Widget Settings
                </a>
            </p>
        </div>
    );
}
