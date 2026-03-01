import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function HelpWidget() {
    const settings = useSelect((select) => select('cdw/store').getSettings());
    const { fetchSettings } = useDispatch('cdw/store');

    useEffect(() => {
        if (!settings.email) {
            fetchSettings();
        }
    }, []);

    const adminUrl = window.cdwData?.adminUrl || '';
    const email = settings?.email || 'support@example.com';
    const docsUrl = settings?.docs_url || 'https://example.com/docs';

    return (
        <div className="cdw-help-widget">
            <p>
                Need help? Contact our support team at{' '}
                <a href={`mailto:${email}`}>{email}</a>
            </p>
            <p>
                Visit our <a href={docsUrl}>documentation</a> for more information.
            </p>
            <p>
                <a href={adminUrl + 'options-general.php?page=cdw-settings'} className="button">
                    Edit Widget Settings
                </a>
            </p>
        </div>
    );
}
