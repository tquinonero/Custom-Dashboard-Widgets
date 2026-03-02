/**
 * Tests for UpdatesWidget.js component (Part 3.5).
 */
import { render, screen } from '@testing-library/react';
import UpdatesWidget from '../../../src/components/UpdatesWidget';
import { useSelect, useDispatch } from '@wordpress/data';

function mockStore({ updates = { core: { count: 0, available: false }, plugins: [], themes: [] }, isLoading = false } = {}) {
    useSelect.mockImplementation((fn) =>
        fn(() => ({
            getUpdates: () => updates,
            isLoading:  () => isLoading,
        }))
    );
    useDispatch.mockReturnValue({ fetchUpdates: jest.fn() });
}

beforeEach(() => mockStore());
afterEach(() => jest.clearAllMocks());

describe('UpdatesWidget', () => {
    it('shows spinner/loading when isLoading=true', () => {
        mockStore({ isLoading: true });
        render(<UpdatesWidget />);
        expect(screen.getByText(/loading updates/i)).toBeInTheDocument();
    });

    it('shows "Good job" message when no updates are available', () => {
        mockStore({
            updates: { core: { available: false }, plugins: [], themes: [] },
        });
        render(<UpdatesWidget />);
        expect(screen.getByText(/good job/i)).toBeInTheDocument();
    });

    it('shows WordPress Core update item when core.available=true', () => {
        mockStore({
            updates: { core: { available: true, count: 1 }, plugins: [], themes: [] },
        });
        render(<UpdatesWidget />);
        expect(screen.getByText(/wordpress core/i)).toBeInTheDocument();
    });

    it('renders plugin update row', () => {
        mockStore({
            updates: {
                core: { available: false },
                plugins: [{ name: 'Foo Plugin', version: '1.0', new_version: '1.1' }],
                themes: [],
            },
        });
        render(<UpdatesWidget />);
        expect(screen.getByText(/foo plugin/i)).toBeInTheDocument();
        // version numbers appear as text nodes inside the <li>; check the full list item
        const item = screen.getByText(/foo plugin/i).closest('li');
        expect(item).toHaveTextContent('1.0');
        expect(item).toHaveTextContent('1.1');
    });

    it('renders theme update row', () => {
        mockStore({
            updates: {
                core: { available: false },
                plugins: [],
                themes: [{ name: 'Bar Theme', version: '2.0', new_version: '2.1' }],
            },
        });
        render(<UpdatesWidget />);
        expect(screen.getByText(/bar theme/i)).toBeInTheDocument();
    });

    it('Core update link contains "update-core.php"', () => {
        mockStore({
            updates: { core: { available: true }, plugins: [], themes: [] },
        });
        render(<UpdatesWidget />);
        const link = screen.getAllByRole('link').find((a) =>
            (a.href || '').includes('update-core.php')
        );
        expect(link).toBeTruthy();
    });

    it('Plugin update link contains "plugins.php"', () => {
        mockStore({
            updates: {
                core: { available: false },
                plugins: [{ name: 'Foo', version: '1.0', new_version: '1.1' }],
                themes: [],
            },
        });
        render(<UpdatesWidget />);
        const links = screen.getAllByRole('link');
        expect(links.some((a) => (a.href || '').includes('plugins.php'))).toBe(true);
    });

    it('Theme update link contains "themes.php"', () => {
        mockStore({
            updates: {
                core: { available: false },
                plugins: [],
                themes: [{ name: 'Bar', version: '2.0', new_version: '2.1' }],
            },
        });
        render(<UpdatesWidget />);
        const links = screen.getAllByRole('link');
        expect(links.some((a) => (a.href || '').includes('themes.php'))).toBe(true);
    });

    it('does not crash when updates.plugins is undefined', () => {
        mockStore({
            updates: { core: { available: false }, themes: [] },
        });
        expect(() => render(<UpdatesWidget />)).not.toThrow();
    });

    it('does not crash when updates.core is undefined', () => {
        mockStore({
            updates: { plugins: [], themes: [] },
        });
        expect(() => render(<UpdatesWidget />)).not.toThrow();
    });
});
