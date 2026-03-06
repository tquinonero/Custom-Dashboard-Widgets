/**
 * Tests for MediaWidget.js component.
 */
import { render, screen } from '@testing-library/react';
import MediaWidget from '../../../src/components/MediaWidget';
import { useSelect, useDispatch } from '@wordpress/data';

function mockStore({ media = [], isLoading = false } = {}) {
    useSelect.mockImplementation((fn) =>
        fn((/*storeName*/) => ({
            getMedia:  () => media,
            isLoading: () => isLoading,
        }))
    );
    useDispatch.mockReturnValue({ fetchMedia: jest.fn() });
}

beforeEach(() => {
    mockStore();
    delete window.cdwData;
});
afterEach(() => jest.clearAllMocks());

// ── Loading state ─────────────────────────────────────────────────────────────
describe('MediaWidget — loading', () => {
    it('shows spinner when isLoading=true and media list is empty', () => {
        mockStore({ isLoading: true, media: [] });
        render(<MediaWidget />);

        expect(screen.getByText(/loading media/i)).toBeInTheDocument();
    });

    it('does not show spinner when isLoading=true but media is already populated', () => {
        mockStore({
            isLoading: true,
            media: [{ id: 1, title: 'Photo', url: 'https://example.com/photo.jpg' }],
        });
        render(<MediaWidget />);

        expect(screen.queryByText(/loading media/i)).not.toBeInTheDocument();
    });
});

// ── Empty state ───────────────────────────────────────────────────────────────
describe('MediaWidget — empty state', () => {
    it('shows "No media found." when media array is empty and not loading', () => {
        mockStore({ media: [], isLoading: false });
        render(<MediaWidget />);

        expect(screen.getByText(/no media found/i)).toBeInTheDocument();
    });
});

// ── Items rendering ───────────────────────────────────────────────────────────
describe('MediaWidget — items', () => {
    const items = [
        { id: 1, title: 'Banner Image',  url: 'https://cdn.example.com/banner.jpg' },
        { id: 2, title: 'Profile Photo', url: 'https://cdn.example.com/profile.png' },
    ];

    beforeEach(() => mockStore({ media: items }));

    it('renders a link for each media item', () => {
        render(<MediaWidget />);

        expect(screen.getByRole('link', { name: /banner image/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /profile photo/i })).toBeInTheDocument();
    });

    it('each media link points to the item URL', () => {
        render(<MediaWidget />);

        const link = screen.getByRole('link', { name: /banner image/i });
        expect(link).toHaveAttribute('href', 'https://cdn.example.com/banner.jpg');
    });

    it('each media link opens in a new tab with noopener noreferrer', () => {
        render(<MediaWidget />);

        const link = screen.getByRole('link', { name: /banner image/i });
        expect(link).toHaveAttribute('target',  '_blank');
        expect(link).toHaveAttribute('rel',     'noopener noreferrer');
    });
});

// ── Footer link ───────────────────────────────────────────────────────────────
describe('MediaWidget — footer link', () => {
    it('always renders a "Go to Media Library" link', () => {
        render(<MediaWidget />);

        expect(screen.getByRole('link', { name: /go to media library/i })).toBeInTheDocument();
    });

    it('Go to Media Library link uses adminUrl from window.cdwData', () => {
        window.cdwData = { adminUrl: 'https://shop.example.com/wp-admin/' };
        render(<MediaWidget />);

        const link = screen.getByRole('link', { name: /go to media library/i });
        expect(link.href).toContain('https://shop.example.com/wp-admin/upload.php');
    });

    it('Go to Media Library link falls back to relative path when cdwData absent', () => {
        render(<MediaWidget />);

        const link = screen.getByRole('link', { name: /go to media library/i });
        expect(link).toHaveAttribute('href', 'upload.php');
    });
});
