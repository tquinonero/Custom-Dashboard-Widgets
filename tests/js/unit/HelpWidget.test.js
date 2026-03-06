/**
 * Tests for HelpWidget.js component.
 */
import { render, screen } from '@testing-library/react';
import HelpWidget from '../../../src/components/HelpWidget';
import { useSelect, useDispatch } from '@wordpress/data';

function mockStore({ settings = {}, isLoading = false } = {}) {
    useSelect.mockImplementation((fn) =>
        fn((/*storeName*/) => ({
            getSettings: () => settings,
            isLoading:   () => isLoading,
        }))
    );
    useDispatch.mockReturnValue({ fetchSettings: jest.fn() });
}

beforeEach(() => {
    mockStore();
    delete window.cdwData;
});
afterEach(() => jest.clearAllMocks());

// ── Rendering ─────────────────────────────────────────────────────────────────
describe('HelpWidget — email', () => {
    it('shows support email with a mailto link when email is set', () => {
        mockStore({ settings: { email: 'support@example.com', docs_url: '' } });
        render(<HelpWidget />);

        const link = screen.getByRole('link', { name: /support@example\.com/i });
        expect(link).toHaveAttribute('href', 'mailto:support@example.com');
    });

    it('does not render email paragraph when email is empty', () => {
        mockStore({ settings: { email: '', docs_url: '' } });
        render(<HelpWidget />);

        expect(screen.queryByText(/contact support/i)).not.toBeInTheDocument();
    });
});

describe('HelpWidget — docs URL', () => {
    it('shows documentation link when docs_url is set', () => {
        mockStore({ settings: { email: '', docs_url: 'https://docs.example.com' } });
        render(<HelpWidget />);

        const link = screen.getByRole('link', { name: /documentation/i });
        expect(link).toHaveAttribute('href', 'https://docs.example.com');
    });

    it('does not render docs paragraph when docs_url is empty', () => {
        mockStore({ settings: { email: '', docs_url: '' } });
        render(<HelpWidget />);

        expect(screen.queryByRole('link', { name: /documentation/i })).not.toBeInTheDocument();
    });
});

describe('HelpWidget — no settings configured', () => {
    it('shows "No support information configured" when email and docs_url are both empty and not loading', () => {
        mockStore({ settings: { email: '', docs_url: '' }, isLoading: false });
        render(<HelpWidget />);

        expect(screen.getByText(/No support information configured/i)).toBeInTheDocument();
    });

    it('does NOT show the fallback message while still loading', () => {
        mockStore({ settings: { email: '', docs_url: '' }, isLoading: true });
        render(<HelpWidget />);

        expect(screen.queryByText(/No support information configured/i)).not.toBeInTheDocument();
    });

    it('shows "Configure settings" link with correct href when no info configured', () => {
        window.cdwData = { adminUrl: 'https://site.com/wp-admin/' };
        mockStore({ settings: { email: '', docs_url: '' }, isLoading: false });
        render(<HelpWidget />);

        const link = screen.getByRole('link', { name: /configure settings/i });
        expect(link.href).toContain('cdw-settings');
    });
});

describe('HelpWidget — Edit Widget Settings button', () => {
    it('shows "Edit Widget Settings" button when email is set', () => {
        mockStore({ settings: { email: 'a@b.com', docs_url: '' } });
        render(<HelpWidget />);

        expect(screen.getByRole('link', { name: /edit widget settings/i })).toBeInTheDocument();
    });

    it('shows "Edit Widget Settings" button when docs_url is set', () => {
        mockStore({ settings: { email: '', docs_url: 'https://docs.ex.com' } });
        render(<HelpWidget />);

        expect(screen.getByRole('link', { name: /edit widget settings/i })).toBeInTheDocument();
    });

    it('button uses adminUrl from window.cdwData', () => {
        window.cdwData = { adminUrl: 'https://mysite.com/wp-admin/' };
        mockStore({ settings: { email: 'x@x.com', docs_url: '' } });
        render(<HelpWidget />);

        const btn = screen.getByRole('link', { name: /edit widget settings/i });
        expect(btn.href).toContain('https://mysite.com/wp-admin/');
    });
});
