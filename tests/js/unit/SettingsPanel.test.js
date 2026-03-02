/**
 * Tests for SettingsPanel.js component (Part 3.4).
 */
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SettingsPanel from '../../../src/components/SettingsPanel';
import { useSelect, useDispatch } from '@wordpress/data';

// ── Helper: configure useSelect return value ──────────────────────────────────
function mockStore({ settings = {}, isLoading = false } = {}) {
    useSelect.mockImplementation((selectorFn) =>
        selectorFn((/*storeName*/) => ({
            getSettings: () => settings,
            isLoading: () => isLoading,
        }))
    );
}

// ── Helper: configure useDispatch return value ────────────────────────────────
let mockFetchSettings;
let mockSaveSettings;

function mockActions({ fetchSettings, saveSettings } = {}) {
    mockFetchSettings = fetchSettings ?? jest.fn();
    mockSaveSettings  = saveSettings  ?? jest.fn().mockResolvedValue({});
    useDispatch.mockReturnValue({
        fetchSettings: mockFetchSettings,
        saveSettings:  mockSaveSettings,
    });
}

beforeEach(() => {
    mockStore();
    mockActions();
});

afterEach(() => {
    jest.clearAllMocks();
    jest.useRealTimers();
});

// ── Rendering ─────────────────────────────────────────────────────────────────
describe('Rendering', () => {
    it('renders email field', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/support email/i)).toBeInTheDocument();
    });

    it('renders docs_url field', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/documentation url/i)).toBeInTheDocument();
    });

    it('renders font_size field', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/widget text size/i)).toBeInTheDocument();
    });

    it('renders bg_color field', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/widget background color/i)).toBeInTheDocument();
    });

    it('renders header_bg_color field', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/widget header background/i)).toBeInTheDocument();
    });

    it('renders header_text_color field', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/widget header text color/i)).toBeInTheDocument();
    });

    it('renders cli_enabled checkbox', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/enable command line widget/i)).toBeInTheDocument();
    });

    it('renders remove_default_widgets checkbox', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/remove default wordpress widgets/i)).toBeInTheDocument();
    });

    it('renders delete_on_uninstall checkbox', () => {
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/delete all data on uninstall/i)).toBeInTheDocument();
    });

    it('submit button shows "Save Changes" when not loading', () => {
        mockStore({ isLoading: false });
        render(<SettingsPanel />);
        expect(screen.getByRole('button', { name: /save changes/i })).toBeInTheDocument();
    });

    it('submit button shows "Saving..." when isLoading=true', () => {
        mockStore({ isLoading: true });
        render(<SettingsPanel />);
        expect(screen.getByRole('button', { name: /saving\.\.\./i })).toBeInTheDocument();
    });

    it('submit button is disabled when isLoading=true', () => {
        mockStore({ isLoading: true });
        render(<SettingsPanel />);
        expect(screen.getByRole('button', { name: /saving\.\.\./i })).toBeDisabled();
    });
});

// ── Form state sync from settings ─────────────────────────────────────────────
describe('Form state sync', () => {
    it('cli_enabled checkbox unchecked when settings.cli_enabled=false', () => {
        mockStore({ settings: { cli_enabled: false } });
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/enable command line widget/i)).not.toBeChecked();
    });

    it('cli_enabled checkbox checked when settings.cli_enabled is undefined', () => {
        mockStore({ settings: {} });
        render(<SettingsPanel />);
        // Default is true (cli_enabled !== false evaluates to true)
        expect(screen.getByLabelText(/enable command line widget/i)).toBeChecked();
    });

    it('delete_on_uninstall checkbox unchecked when settings.delete_on_uninstall=false', () => {
        mockStore({ settings: { delete_on_uninstall: false } });
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/delete all data on uninstall/i)).not.toBeChecked();
    });

    it('delete_on_uninstall checkbox checked when settings.delete_on_uninstall is undefined', () => {
        mockStore({ settings: {} });
        render(<SettingsPanel />);
        expect(screen.getByLabelText(/delete all data on uninstall/i)).toBeChecked();
    });
});

// ── User interaction ──────────────────────────────────────────────────────────
describe('User interaction', () => {
    it('typing in email field updates formData.email', () => {
        mockStore({ settings: { email: '' } });
        render(<SettingsPanel />);
        const input = screen.getByLabelText(/support email/i);
        fireEvent.change(input, { target: { value: 'hello@example.com' } });
        expect(input.value).toBe('hello@example.com');
    });

    it('checking cli_enabled sets it to true', () => {
        mockStore({ settings: { cli_enabled: false } });
        render(<SettingsPanel />);
        const checkbox = screen.getByLabelText(/enable command line widget/i);
        expect(checkbox).not.toBeChecked();
        fireEvent.click(checkbox);
        expect(checkbox).toBeChecked();
    });

    it('unchecking cli_enabled sets it to false', () => {
        mockStore({ settings: { cli_enabled: true } });
        render(<SettingsPanel />);
        const checkbox = screen.getByLabelText(/enable command line widget/i);
        expect(checkbox).toBeChecked();
        fireEvent.click(checkbox);
        expect(checkbox).not.toBeChecked();
    });

    it('any field change clears the "Settings saved!" message', async () => {
        mockActions({ saveSettings: jest.fn().mockResolvedValue({}) });
        mockStore();
        render(<SettingsPanel />);

        // Submit to get the saved message
        const submitBtn = screen.getByRole('button', { name: /save changes/i });
        fireEvent.click(submitBtn);
        await waitFor(() => expect(screen.getByText(/settings saved!/i)).toBeInTheDocument());

        // Now change a field
        const input = screen.getByLabelText(/support email/i);
        fireEvent.change(input, { target: { value: 'x' } });
        expect(screen.queryByText(/settings saved!/i)).not.toBeInTheDocument();
    });
});

// ── handleSubmit ──────────────────────────────────────────────────────────────
describe('handleSubmit', () => {
    it('calls saveSettings with current formData on submit', async () => {
        mockStore({ settings: { email: 'existing@test.com' } });
        const mockSave = jest.fn().mockResolvedValue({});
        mockActions({ saveSettings: mockSave });
        render(<SettingsPanel />);

        const btn = screen.getByRole('button', { name: /save changes/i });
        fireEvent.click(btn);

        await waitFor(() => expect(mockSave).toHaveBeenCalledTimes(1));
        expect(mockSave).toHaveBeenCalledWith(
            expect.objectContaining({ email: 'existing@test.com' })
        );
    });

    it('shows "Settings saved!" message on success', async () => {
        mockActions({ saveSettings: jest.fn().mockResolvedValue({}) });
        render(<SettingsPanel />);
        const btn = screen.getByRole('button', { name: /save changes/i });
        fireEvent.click(btn);
        await waitFor(() =>
            expect(screen.getByText(/settings saved!/i)).toBeInTheDocument()
        );
    });

    it('"Settings saved!" message disappears after 3000ms', async () => {
        jest.useFakeTimers();
        mockActions({ saveSettings: jest.fn().mockResolvedValue({}) });
        render(<SettingsPanel />);
        const btn = screen.getByRole('button', { name: /save changes/i });
        fireEvent.click(btn);
        await waitFor(() => screen.getByText(/settings saved!/i));

        act(() => jest.runAllTimers());

        expect(screen.queryByText(/settings saved!/i)).not.toBeInTheDocument();
        jest.useRealTimers();
    });

    it('shows error message on failure', async () => {
        mockActions({
            saveSettings: jest.fn().mockRejectedValue(new Error('Server boom')),
        });
        render(<SettingsPanel />);
        const btn = screen.getByRole('button', { name: /save changes/i });
        fireEvent.click(btn);
        await waitFor(() =>
            expect(screen.getByText(/server boom/i)).toBeInTheDocument()
        );
    });
});
