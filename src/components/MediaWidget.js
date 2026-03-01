import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function MediaWidget() {
    const media = useSelect((select) => select('cdw/store').getMedia());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('media'));
    const { fetchMedia } = useDispatch('cdw/store');

    useEffect(() => {
        fetchMedia(10);
    }, []);

    if (isLoading && media.length === 0) {
        return <div className="cdw-loading">Loading media...</div>;
    }

    return (
        <div className="cdw-media-widget">
            {media.length === 0 ? (
                <p>No media found.</p>
            ) : (
                <ul className="cdw-media-list">
                    {media.map((item) => (
                        <li key={item.id}>
                            <a href={item.url} target="_blank" rel="noopener noreferrer">
                                {item.title}
                            </a>
                        </li>
                    ))}
                </ul>
            )}
            <p>
                <a href={(window.cdwData?.adminUrl || '') + 'upload.php'} className="button button-primary">
                    Go to Media Library
                </a>
            </p>
        </div>
    );
}
