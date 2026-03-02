/**
 * Tests for CommandWidget.js component (Part 3.7).
 */
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import CommandWidget from '../../../src/components/CommandWidget';
import apiFetch from '@wordpress/api-fetch';

// Default mock: history=[], commands=[]
function mockDefaultFetch() {
    apiFetch.mockImplementation((opts) => {
        if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
            return Promise.resolve([]);
        }
        if (opts.path && opts.path.includes('/cdw/v1/cli/commands')) {
            return Promise.resolve([]);
        }
        if (opts.path && opts.path.includes('/cdw/v1/cli/execute')) {
            return Promise.resolve({ output: 'ok', success: true });
        }
        return Promise.resolve({});
    });
}

// Helper that loads with given history entries
function mockWithHistory(entries) {
    apiFetch.mockImplementation((opts) => {
        if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
            return Promise.resolve(entries);
        }
        if (opts.path && opts.path.includes('/cdw/v1/cli/commands')) {
            return Promise.resolve([]);
        }
        return Promise.resolve({ output: 'ok', success: true });
    });
}

// Helper that loads with given command definitions
function mockWithCommands(categories) {
    apiFetch.mockImplementation((opts) => {
        if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
            return Promise.resolve([]);
        }
        if (opts.path && opts.path.includes('/cdw/v1/cli/commands')) {
            return Promise.resolve(categories);
        }
        return Promise.resolve({ output: 'ok', success: true });
    });
}

beforeEach(() => mockDefaultFetch());
afterEach(() => jest.clearAllMocks());

// ── Rendering ─────────────────────────────────────────────────────────────────
describe('Rendering', () => {
    it('shows welcome panel with "ADMIN CLI" when history is empty', async () => {
        render(<CommandWidget />);
        await waitFor(() =>
            expect(screen.getByText('ADMIN CLI')).toBeInTheDocument()
        );
    });

    it('shows history entries when history has items', async () => {
        const historyEntries = [
            { command: 'plugin list', output: 'Listing plugins', success: true, timestamp: Date.now() },
        ];
        mockWithHistory(historyEntries);
        render(<CommandWidget />);
        await waitFor(() =>
            expect(screen.getByText('plugin list')).toBeInTheDocument()
        );
        expect(screen.queryByText('ADMIN CLI')).not.toBeInTheDocument();
    });
});

// ── loadHistory ───────────────────────────────────────────────────────────────
describe('loadHistory()', () => {
    it('apiFetch returns array → entries rendered', async () => {
        const entries = [
            { command: 'user list', output: 'Users here', success: true, timestamp: Date.now() },
        ];
        mockWithHistory(entries);
        render(<CommandWidget />);
        await waitFor(() =>
            expect(screen.getByText('user list')).toBeInTheDocument()
        );
    });

    it('apiFetch returns non-array → history stays empty (no crash)', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
                return Promise.resolve('not an array');
            }
            return Promise.resolve([]);
        });
        expect(() => render(<CommandWidget />)).not.toThrow();
        await waitFor(() =>
            expect(screen.getByText('ADMIN CLI')).toBeInTheDocument()
        );
    });

    it('apiFetch throws → history stays empty (no crash)', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
                return Promise.reject(new Error('Network failure'));
            }
            return Promise.resolve([]);
        });
        expect(() => render(<CommandWidget />)).not.toThrow();
        await waitFor(() =>
            expect(screen.getByText('ADMIN CLI')).toBeInTheDocument()
        );
        // Component catches the error and logs it — that is expected behaviour
        expect(console).toHaveErrored();
    });
});

// ── loadCommandDefinitions ────────────────────────────────────────────────────
describe('loadCommandDefinitions()', () => {
    it('returns categorised array → flattened for autocomplete (type in input to see suggestions)', async () => {
        mockWithCommands([
            { category: 'Plugin', commands: [{ name: 'plugin list', description: 'List plugins' }] },
            { category: 'User', commands: [{ name: 'user list', description: 'List users' }] },
        ]);
        render(<CommandWidget />);
        await waitFor(() => {}); // let commands load

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: 'plugin' } });

        await waitFor(() =>
            expect(screen.getByText('plugin list')).toBeInTheDocument()
        );
    });

    it('returns non-array → no crash', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/commands')) {
                return Promise.resolve('not-an-array');
            }
            return Promise.resolve([]);
        });
        expect(() => render(<CommandWidget />)).not.toThrow();
        await waitFor(() =>
            expect(screen.getByText('ADMIN CLI')).toBeInTheDocument()
        );
    });
});

// ── updateSuggestions ─────────────────────────────────────────────────────────
describe('updateSuggestions()', () => {
    const COMMANDS = [
        { category: 'Plugin', commands: [
            { name: 'plugin list', description: 'List' },
            { name: 'plugin activate', description: 'Activate' },
            { name: 'plugin deactivate', description: 'Deactivate' },
            { name: 'plugin update', description: 'Update' },
            { name: 'plugin delete', description: 'Delete' },
            { name: 'plugin install', description: 'Install' },
            { name: 'plugin status', description: 'Status' }, // 7th — should be cut
        ]},
        { category: 'User', commands: [
            { name: 'user list', description: 'List users' },
        ]},
    ];

    beforeEach(() => mockWithCommands(COMMANDS));

    async function renderAndLoadCommands() {
        render(<CommandWidget />);
        // Wait for commands to load
        await waitFor(() => {});
        return screen.getByPlaceholderText(/type a command/i);
    }

    it('input "plug" shows commands starting with "plug"', async () => {
        const input = await renderAndLoadCommands();
        fireEvent.change(input, { target: { value: 'plug' } });
        await waitFor(() =>
            expect(screen.getByText('plugin list')).toBeInTheDocument()
        );
    });

    it('input "list" shows commands containing "list"', async () => {
        const input = await renderAndLoadCommands();
        fireEvent.change(input, { target: { value: 'list' } });
        await waitFor(() =>
            expect(screen.getByText('plugin list')).toBeInTheDocument()
        );
        expect(screen.getByText('user list')).toBeInTheDocument();
    });

    it('empty input → no suggestions', async () => {
        const input = await renderAndLoadCommands();
        // Type something first to get suggestions
        fireEvent.change(input, { target: { value: 'plug' } });
        await waitFor(() => screen.getByText('plugin list'));

        // Then clear it
        fireEvent.change(input, { target: { value: '' } });
        await waitFor(() =>
            expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
        );
    });

    it('shows at most 6 suggestions', async () => {
        const input = await renderAndLoadCommands();
        fireEvent.change(input, { target: { value: 'plugin' } });
        await waitFor(() => screen.getByText('plugin list'));

        // There are 7 plugin commands but only 6 should be shown
        const listbox = screen.getByRole('listbox');
        expect(listbox.querySelectorAll('li').length).toBeLessThanOrEqual(6);
    });
});

// ── executeCommand ────────────────────────────────────────────────────────────
describe('executeCommand()', () => {
    it('whitespace-only command → apiFetch NOT called for execute', async () => {
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('ADMIN CLI'));

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: '   ' } });
        const form = input.closest('form');
        fireEvent.submit(form);

        // apiFetch should only have been called for history and commands (not execute)
        const executeCalls = apiFetch.mock.calls.filter(
            ([opts]) => opts.path && opts.path.includes('/cdw/v1/cli/execute')
        );
        expect(executeCalls.length).toBe(0);
    });

    it('response with "output" key → output shown', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/execute')) {
                return Promise.resolve({ output: 'Plugin list result', success: true });
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('ADMIN CLI'));

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: 'plugin list' } });
        const form = input.closest('form');
        fireEvent.submit(form);

        await waitFor(() =>
            expect(screen.getByText('Plugin list result')).toBeInTheDocument()
        );
    });

    it('response with "message" key → message shown as output', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/execute')) {
                return Promise.resolve({ message: 'CLI is disabled', code: 'cli_disabled' });
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('ADMIN CLI'));

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: 'plugin list' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() =>
            expect(screen.getByText(/CLI is disabled/i)).toBeInTheDocument()
        );
    });

    it('response with success=false → entry gets error styling', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/execute')) {
                return Promise.resolve({ output: 'Error occurred', success: false });
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('ADMIN CLI'));

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: 'bad cmd' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => screen.getByText('Error occurred'));
        const entry = screen.getByText('Error occurred').closest('.cdw-command-entry');
        expect(entry).toHaveClass('error');
    });

    it('apiFetch throws → entry shows "Error:" and widget recovers', async () => {
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/execute')) {
                return Promise.reject(new Error('Request failed'));
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('ADMIN CLI'));

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: 'fail cmd' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() =>
            expect(screen.getByText(/Error:/i)).toBeInTheDocument()
        );
        // Input should be re-enabled for the next command
        expect(input).not.toBeDisabled();
    });
});

// ── clearHistory ──────────────────────────────────────────────────────────────
describe('clearHistory()', () => {
    it('calls apiFetch with DELETE method and /cdw/v1/cli/history path', async () => {
        mockWithHistory([
            { command: 'plugin list', output: 'ok', success: true, timestamp: Date.now() },
        ]);
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('plugin list'));

        const clearBtn = screen.getByText(/clear/i);
        fireEvent.click(clearBtn);

        await waitFor(() => {
            const deleteCalls = apiFetch.mock.calls.filter(
                ([opts]) => opts.method === 'DELETE' && opts.path.includes('/cdw/v1/cli/history')
            );
            expect(deleteCalls.length).toBeGreaterThanOrEqual(1);
        });
    });

    it('success → local history state cleared (welcome panel shown)', async () => {
        mockWithHistory([
            { command: 'plugin list', output: 'ok', success: true, timestamp: Date.now() },
        ]);
        apiFetch.mockImplementation((opts) => {
            if (opts.method === 'DELETE') return Promise.resolve({});
            if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
                return Promise.resolve([
                    { command: 'plugin list', output: 'ok', success: true, timestamp: Date.now() },
                ]);
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('plugin list'));

        fireEvent.click(screen.getByText(/clear/i));

        await waitFor(() =>
            expect(screen.getByText('ADMIN CLI')).toBeInTheDocument()
        );
    });

    it('apiFetch throws → local history still cleared', async () => {
        mockWithHistory([
            { command: 'test cmd', output: 'out', success: true, timestamp: Date.now() },
        ]);
        apiFetch.mockImplementation((opts) => {
            if (opts.method === 'DELETE') return Promise.reject(new Error('Network error'));
            if (opts.path && opts.path.includes('/cdw/v1/cli/history')) {
                return Promise.resolve([
                    { command: 'test cmd', output: 'out', success: true, timestamp: Date.now() },
                ]);
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('test cmd'));

        fireEvent.click(screen.getByText(/clear/i));

        await waitFor(() =>
            expect(screen.getByText('ADMIN CLI')).toBeInTheDocument()
        );
        // Component catches the DELETE error and logs it — that is expected behaviour
        expect(console).toHaveErrored();
    });
});

// ── Keyboard navigation ───────────────────────────────────────────────────────
describe('Keyboard navigation', () => {
    const COMMANDS = [
        { category: 'P', commands: [
            { name: 'plugin activate', description: 'Activate a plugin' },
            { name: 'plugin deactivate', description: 'Deactivate a plugin' },
            { name: 'plugin list', description: 'List plugins' },
        ]},
    ];

    async function renderAndGetSuggestions() {
        mockWithCommands(COMMANDS);
        render(<CommandWidget />);
        // Wait for component to settle
        await waitFor(() => {});
        const input = screen.getByPlaceholderText(/type a command/i);
        // Type to trigger suggestions
        fireEvent.change(input, { target: { value: 'plugin' } });
        // Wait for suggestions to appear
        await waitFor(() => screen.getByRole('listbox'));
        return input;
    }

    it('ArrowDown highlights second item', async () => {
        const input = await renderAndGetSuggestions();
        // First ArrowDown: index 0
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        // Second ArrowDown: index 1
        fireEvent.keyDown(input, { key: 'ArrowDown' });

        const items = screen.getByRole('listbox').querySelectorAll('.cdw-command-suggestion');
        expect(items[1]).toHaveClass('is-active');
    });

    it('ArrowUp at index 0 wraps to last item', async () => {
        const input = await renderAndGetSuggestions();
        // Move to index 0
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        // ArrowUp from 0 should wrap to last
        fireEvent.keyDown(input, { key: 'ArrowUp' });

        const items = screen.getByRole('listbox').querySelectorAll('.cdw-command-suggestion');
        expect(items[items.length - 1]).toHaveClass('is-active');
    });

    it('Tab with highlighted suggestion → command set to name + space, suggestions cleared', async () => {
        const input = await renderAndGetSuggestions();
        // Press ArrowDown to highlight first item
        fireEvent.keyDown(input, { key: 'ArrowDown' });
        // Press Tab to select
        fireEvent.keyDown(input, { key: 'Tab' });

        await waitFor(() =>
            expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
        );
        // Input should end with a space (name + ' ')
        expect(input.value).toMatch(/\S+ $/);
    });

    it('Enter with no suggestions calls executeCommand', async () => {
        mockDefaultFetch();
        apiFetch.mockImplementation((opts) => {
            if (opts.path && opts.path.includes('/cdw/v1/cli/execute')) {
                return Promise.resolve({ output: 'result', success: true });
            }
            return Promise.resolve([]);
        });
        render(<CommandWidget />);
        await waitFor(() => screen.getByText('ADMIN CLI'));

        const input = screen.getByPlaceholderText(/type a command/i);
        fireEvent.change(input, { target: { value: 'user list' } });
        fireEvent.keyDown(input, { key: 'Enter' });

        await waitFor(() =>
            expect(screen.getByText('result')).toBeInTheDocument()
        );
    });
});
