export default function QuickLinksWidget() {
    const adminUrl = window.cdwData?.adminUrl || '';
    const adminMenuData = window.cdwData?.adminMenuData || [];

    const getHref = (href) => {
        if (href.startsWith('http://') || href.startsWith('https://') || href.startsWith('//')) {
            return href;
        }
        return adminUrl + href;
    };

    const renderCategory = (category) => (
        <div key={category.label} className="cdw-quicklinks-section">
            <h4>
                <span className={`dashicons ${category.icon}`} style={{ marginRight: '6px', fontSize: '16px' }}></span>
                {category.label}
            </h4>
            <div className="cdw-quicklinks-buttons">
                {category.items.map((item) => (
                    <a key={item.href} href={getHref(item.href)} className="button">
                        {item.label}
                    </a>
                ))}
            </div>
        </div>
    );

    if (adminMenuData.length > 0) {
        return (
            <div className="cdw-quicklinks-widget">
                {adminMenuData.map(renderCategory)}
            </div>
        );
    }

    return (
        <div className="cdw-quicklinks-widget">
            <div className="cdw-quicklinks-section">
                <h4>Content</h4>
                <div className="cdw-quicklinks-buttons">
                    <a href={adminUrl + 'edit.php'} className="button">Posts</a>
                    <a href={adminUrl + 'post-new.php'} className="button">Add New</a>
                    <a href={adminUrl + 'edit.php?post_type=page'} className="button">Pages</a>
                    <a href={adminUrl + 'upload.php'} className="button">Media</a>
                </div>
            </div>
            <div className="cdw-quicklinks-section">
                <h4>Quick Access</h4>
                <div className="cdw-quicklinks-buttons">
                    <a href={adminUrl + 'themes.php'} className="button">Appearance</a>
                    <a href={adminUrl + 'users.php'} className="button">Users</a>
                    <a href={adminUrl + 'tools.php'} className="button">Tools</a>
                    <a href={adminUrl + 'options-general.php'} className="button">Settings</a>
                </div>
            </div>
            <div className="cdw-quicklinks-section">
                <h4>Tools</h4>
                <div className="cdw-quicklinks-buttons">
                    <a href={adminUrl + 'import.php'} className="button">Import</a>
                    <a href={adminUrl + 'export.php'} className="button">Export</a>
                    <a href={adminUrl + 'site-health.php'} className="button">Site Health</a>
                </div>
            </div>
        </div>
    );
}
