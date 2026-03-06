/**
 * Tests for QuickLinksWidget.js component.
 *
 * QuickLinksWidget is a pure presentational component (no store, no side-effects),
 * so tests focus entirely on rendering correctness.
 */
import { render, screen } from '@testing-library/react';
import QuickLinksWidget from '../../../src/components/QuickLinksWidget';

beforeEach(() => { delete window.cdwData; });
afterEach(() => jest.clearAllMocks());

// ── Quick Access section ───────────────────────────────────────────────────────
describe('QuickLinksWidget — Quick Access', () => {
    it('renders Appearance link', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('link', { name: /^appearance$/i })).toBeInTheDocument();
    });

    it('renders Users link', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('link', { name: /^users$/i })).toBeInTheDocument();
    });

    it('renders Tools link in Quick Access section', () => {
        render(<QuickLinksWidget />);
        // At least one link with text "Tools" must exist.
        const toolLinks = screen.getAllByRole('link', { name: /^tools$/i });
        expect(toolLinks.length).toBeGreaterThan(0);
    });

    it('renders Settings link', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('link', { name: /^settings$/i })).toBeInTheDocument();
    });
});

// ── Tools section ─────────────────────────────────────────────────────────────
describe('QuickLinksWidget — Tools section', () => {
    it('renders Import link', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('link', { name: /^import$/i })).toBeInTheDocument();
    });

    it('renders Export link', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('link', { name: /^export$/i })).toBeInTheDocument();
    });

    it('renders Site Health link', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('link', { name: /^site health$/i })).toBeInTheDocument();
    });
});

// ── adminUrl usage ────────────────────────────────────────────────────────────
describe('QuickLinksWidget — adminUrl', () => {
    it('Appearance link uses adminUrl from window.cdwData', () => {
        window.cdwData = { adminUrl: 'https://mysite.net/wp-admin/' };
        render(<QuickLinksWidget />);

        const appearance = screen.getByRole('link', { name: /^appearance$/i });
        expect(appearance.href).toBe('https://mysite.net/wp-admin/themes.php');
    });

    it('Users link uses adminUrl from window.cdwData', () => {
        window.cdwData = { adminUrl: 'https://mysite.net/wp-admin/' };
        render(<QuickLinksWidget />);

        const users = screen.getByRole('link', { name: /^users$/i });
        expect(users.href).toBe('https://mysite.net/wp-admin/users.php');
    });

    it('falls back gracefully when window.cdwData is undefined', () => {
        // cdwData deleted in beforeEach — rendering should not throw.
        expect(() => render(<QuickLinksWidget />)).not.toThrow();
    });
});

// ── Section headings ──────────────────────────────────────────────────────────
describe('QuickLinksWidget — section headings', () => {
    it('renders "Quick Access" heading', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByText('Quick Access')).toBeInTheDocument();
    });

    it('renders "Tools" heading', () => {
        render(<QuickLinksWidget />);
        expect(screen.getByRole('heading', { name: 'Tools' })).toBeInTheDocument();
    });
});
