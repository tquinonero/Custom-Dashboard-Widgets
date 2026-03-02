import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

export default function PostsWidget() {
    const posts = useSelect((select) => select('cdw/store').getPosts());
    const isLoading = useSelect((select) => select('cdw/store').isLoading('posts'));
    const { fetchPosts } = useDispatch('cdw/store');

    useEffect(() => {
        fetchPosts(10);
    }, []);

    if (isLoading && posts.length === 0) {
        return <div className="cdw-loading">Loading posts...</div>;
    }

    return (
        <div className="cdw-posts-widget">
            {posts.length === 0 ? (
                <p>No posts found.</p>
            ) : (
                <ul className="cdw-posts-list">
                    {posts.map((post) => (
                        <li key={post.id}>
                            <a href={post.permalink} target="_blank" rel="noopener noreferrer">
                                {post.title}
                            </a>
                        </li>
                    ))}
                </ul>
            )}
            <div className="cdw-posts-links">
                <a href={(window.cdwData?.adminUrl || '') + 'edit.php'} className="button button-primary">
                    All Posts
                </a>
                <a href={(window.cdwData?.adminUrl || '') + 'post-new.php'} className="button">
                    Add New
                </a>
                <a href={(window.cdwData?.adminUrl || '') + 'edit-tags.php?taxonomy=category'} className="button">
                    Categories
                </a>
                <a href={(window.cdwData?.adminUrl || '') + 'edit-tags.php?taxonomy=post_tag'} className="button">
                    Tags
                </a>
            </div>
        </div>
    );
}
