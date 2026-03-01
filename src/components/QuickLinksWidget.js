import { useSelect, useDispatch } from '@wordpress/data';

export default function QuickLinksWidget() {
    const users = useSelect((select) => select('cdw/store').getUsers());

    const adminUrl = window.cdwData?.adminUrl || '';
    
    const quickLinks = [
        { label: 'Appearance', href: adminUrl + 'themes.php', primary: true },
        { label: 'Users', href: adminUrl + 'users.php', primary: true },
        { label: 'Tools', href: adminUrl + 'tools.php', primary: false },
        { label: 'Settings', href: adminUrl + 'options-general.php', primary: false },
    ];

    return (
        <div className="cdw-quicklinks-widget">
            <div className="cdw-quicklinks-section">
                <h4>Quick Access</h4>
                <div className="cdw-quicklinks-buttons">
                    {quickLinks.map((link, index) => (
                        <a
                            key={index}
                            href={link.href}
                            className={`button ${link.primary ? 'button-primary' : ''}`}
                        >
                            {link.label}
                        </a>
                    ))}
                </div>
            </div>

            <div className="cdw-quicklinks-section">
                <h4>Tools</h4>
                <div className="cdw-quicklinks-buttons">
                    <a href={adminUrl + 'tools.php'} className="button">Tools</a>
                    <a href={adminUrl + 'import.php'} className="button">Import</a>
                    <a href={adminUrl + 'export.php'} className="button">Export</a>
                    <a href={adminUrl + 'site-health.php'} className="button">Site Health</a>
                </div>
            </div>
        </div>
    );
}
