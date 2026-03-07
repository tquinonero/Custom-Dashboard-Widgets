export default function ToolsOtherWidget() {
    const adminUrl = window.cdwData?.adminUrl || '';
    const adminToolsData = window.cdwData?.adminToolsData || [];

    if ( adminToolsData.length === 0 ) {
        return null;
    }

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

    return (
        <div className="cdw-quicklinks-widget">
            {adminToolsData.map(renderCategory)}
        </div>
    );
}
