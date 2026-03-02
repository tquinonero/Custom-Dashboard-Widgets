/**
 * Tests for PostsWidget.js component (Part 3.6).
 */
import { render, screen } from '@testing-library/react';
import PostsWidget from '../../../src/components/PostsWidget';
import { useSelect, useDispatch } from '@wordpress/data';

function mockStore({ posts = [], isLoading = false } = {}) {
    useSelect.mockImplementation((fn) =>
        fn(() => ({
            getPosts:  () => posts,
            isLoading: () => isLoading,
        }))
    );
    useDispatch.mockReturnValue({ fetchPosts: jest.fn() });
}

beforeEach(() => mockStore());
afterEach(() => jest.clearAllMocks());

describe('PostsWidget', () => {
    it('shows spinner when isLoading=true and posts=[]', () => {
        mockStore({ isLoading: true, posts: [] });
        render(<PostsWidget />);
        expect(screen.getByText(/loading posts/i)).toBeInTheDocument();
    });

    it('shows "No posts found." when posts=[]', () => {
        mockStore({ isLoading: false, posts: [] });
        render(<PostsWidget />);
        expect(screen.getByText(/no posts found/i)).toBeInTheDocument();
    });

    it('renders a link for each post with correct href', () => {
        mockStore({
            posts: [{ id: 1, title: 'Hello World', permalink: 'https://example.com/hello' }],
        });
        render(<PostsWidget />);
        const link = screen.getByRole('link', { name: /hello world/i });
        expect(link).toHaveAttribute('href', 'https://example.com/hello');
    });

    it('post link has target="_blank" and rel="noopener noreferrer"', () => {
        mockStore({
            posts: [{ id: 1, title: 'Test Post', permalink: 'https://example.com/test' }],
        });
        render(<PostsWidget />);
        const link = screen.getByRole('link', { name: /test post/i });
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('renders static "All Posts" link', () => {
        render(<PostsWidget />);
        expect(screen.getByText('All Posts')).toBeInTheDocument();
    });

    it('renders static "Add New" link', () => {
        render(<PostsWidget />);
        expect(screen.getByText('Add New')).toBeInTheDocument();
    });

    it('renders static "Categories" link', () => {
        render(<PostsWidget />);
        expect(screen.getByText('Categories')).toBeInTheDocument();
    });

    it('renders static "Tags" link', () => {
        render(<PostsWidget />);
        expect(screen.getByText('Tags')).toBeInTheDocument();
    });
});
