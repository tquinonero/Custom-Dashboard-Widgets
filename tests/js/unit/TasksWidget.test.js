/**
 * Tests for TasksWidget.js component.
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import TasksWidget from '../../../src/components/TasksWidget';
import { useSelect, useDispatch } from '@wordpress/data';

// ── Store helpers ──────────────────────────────────────────────────────────────
let mockAddTask;
let mockRemoveTask;
let mockFetchTasks;
let mockFetchUsers;

function mockStore({
    tasks     = [],
    users     = [],
    isLoading = false,
    error     = null,
} = {}) {
    useSelect.mockImplementation((fn) =>
        fn((/*storeName*/) => ({
            getTasks:  () => tasks,
            getUsers:  () => users,
            isLoading: () => isLoading,
            getError:  () => error,
        }))
    );

    mockAddTask    = jest.fn().mockResolvedValue({});
    mockRemoveTask = jest.fn().mockResolvedValue({});
    mockFetchTasks = jest.fn();
    mockFetchUsers = jest.fn();

    useDispatch.mockReturnValue({
        fetchTasks:  mockFetchTasks,
        fetchUsers:  mockFetchUsers,
        addTask:     mockAddTask,
        removeTask:  mockRemoveTask,
    });
}

// A fixed timestamp so calculateTimeAgo always produces a deterministic string.
const FIXED_TS = Math.floor(Date.now() / 1000) - 60; // 1 minute ago

beforeEach(() => mockStore());
afterEach(() => jest.clearAllMocks());

// ── Loading state ─────────────────────────────────────────────────────────────
describe('TasksWidget — loading', () => {
    it('shows loading spinner when isLoading=true and tasks=[]]', () => {
        mockStore({ isLoading: true, tasks: [] });
        render(<TasksWidget />);

        expect(screen.getByText(/loading tasks/i)).toBeInTheDocument();
    });

    it('does not show spinner when tasks are already populated', () => {
        mockStore({
            isLoading: true,
            tasks: [{ name: 'Existing task', timestamp: FIXED_TS }],
        });
        render(<TasksWidget />);

        expect(screen.queryByText(/loading tasks/i)).not.toBeInTheDocument();
    });
});

// ── Error state ───────────────────────────────────────────────────────────────
describe('TasksWidget — error', () => {
    it('shows error message when error state is set', () => {
        mockStore({ error: 'Server unavailable' });
        render(<TasksWidget />);

        expect(screen.getByText(/server unavailable/i)).toBeInTheDocument();
    });

    it('does not render the task table when in error state', () => {
        mockStore({ error: 'Oops' });
        render(<TasksWidget />);

        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
});

// ── Empty tasks ───────────────────────────────────────────────────────────────
describe('TasksWidget — empty tasks', () => {
    it('shows "No tasks yet" when task list is empty', () => {
        render(<TasksWidget />);

        expect(screen.getByText(/no tasks yet/i)).toBeInTheDocument();
    });

    it('renders the table headers even when empty', () => {
        render(<TasksWidget />);

        expect(screen.getByText('Task')).toBeInTheDocument();
        expect(screen.getByText('Added')).toBeInTheDocument();
    });
});

// ── Tasks rendering ───────────────────────────────────────────────────────────
describe('TasksWidget — task list', () => {
    it('renders a row for each task', () => {
        mockStore({
            tasks: [
                { name: 'Update plugins',  timestamp: FIXED_TS },
                { name: 'Backup database', timestamp: FIXED_TS },
            ],
        });
        render(<TasksWidget />);

        expect(screen.getByText('Update plugins')).toBeInTheDocument();
        expect(screen.getByText('Backup database')).toBeInTheDocument();
    });

    it('shows "Me" as assignee when created_by is absent', () => {
        mockStore({ tasks: [{ name: 'My task', timestamp: FIXED_TS }] });
        render(<TasksWidget />);

        expect(screen.getByText('Me')).toBeInTheDocument();
    });

    it('shows user display_name when created_by matches a user in the store', () => {
        mockStore({
            tasks: [{ name: 'Assigned task', timestamp: FIXED_TS, created_by: 3 }],
            users: [{ id: 3, display_name: 'Alice' }],
        });
        render(<TasksWidget />);

        expect(screen.getAllByText('Alice').length).toBeGreaterThan(0);
    });

    it('shows "Unknown" when created_by does not match any user', () => {
        mockStore({
            tasks: [{ name: 'Ghost task', timestamp: FIXED_TS, created_by: 999 }],
            users: [{ id: 1, display_name: 'Bob' }],
        });
        render(<TasksWidget />);

        expect(screen.getByText('Unknown')).toBeInTheDocument();
    });

    it('renders a remove (×) button for each task', () => {
        mockStore({
            tasks: [
                { name: 'Task A', timestamp: FIXED_TS },
                { name: 'Task B', timestamp: FIXED_TS },
            ],
        });
        render(<TasksWidget />);

        const buttons = screen.getAllByRole('button', { name: /remove task/i });
        expect(buttons).toHaveLength(2);
    });
});

// ── Add task ──────────────────────────────────────────────────────────────────
describe('TasksWidget — adding a task', () => {
    it('Add button is disabled when the input is empty', () => {
        render(<TasksWidget />);

        expect(screen.getByRole('button', { name: /^add$/i })).toBeDisabled();
    });

    it('Add button is enabled after entering text', () => {
        render(<TasksWidget />);

        fireEvent.change(screen.getByPlaceholderText(/add new task/i), { target: { value: 'New task' } });

        expect(screen.getByRole('button', { name: /^add$/i })).toBeEnabled();
    });

    it('clicking Add calls addTask and clears the input', async () => {
        render(<TasksWidget />);

        const input = screen.getByPlaceholderText(/add new task/i);
        fireEvent.change(input, { target: { value: 'Deploy release' } });
        fireEvent.click(screen.getByRole('button', { name: /^add$/i }));

        await waitFor(() => expect(mockAddTask).toHaveBeenCalledTimes(1));
        await waitFor(() => expect(input.value).toBe(''));
    });

    it('pressing Enter in the input calls addTask', async () => {
        render(<TasksWidget />);

        const input = screen.getByPlaceholderText(/add new task/i);
        fireEvent.change(input, { target: { value: 'Keyboard task' } });
        fireEvent.keyDown(input, { key: 'Enter' });

        await waitFor(() => expect(mockAddTask).toHaveBeenCalledTimes(1));
    });

    it('whitespace-only input does NOT call addTask', async () => {
        render(<TasksWidget />);

        const input = screen.getByPlaceholderText(/add new task/i);
        fireEvent.change(input, { target: { value: '   ' } });
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(mockAddTask).not.toHaveBeenCalled();
    });

    it('addTask is called with the full tasks list and null assigneeId when no assignee selected', async () => {
        mockStore({
            tasks: [{ name: 'Existing', timestamp: FIXED_TS }],
        });
        render(<TasksWidget />);

        const input = screen.getByPlaceholderText(/add new task/i);
        fireEvent.change(input, { target: { value: 'New task' } });
        fireEvent.click(screen.getByRole('button', { name: /^add$/i }));

        await waitFor(() => expect(mockAddTask).toHaveBeenCalledTimes(1));
        const [taskArg, assigneeArg] = mockAddTask.mock.calls[0];
        expect(assigneeArg).toBeNull();
        expect(taskArg.length).toBe(2); // existing + new
    });
});

// ── Remove task ───────────────────────────────────────────────────────────────
describe('TasksWidget — removing a task', () => {
    it('clicking × calls removeTask with the remaining tasks', async () => {
        const tasks = [
            { name: 'Keep me',   timestamp: FIXED_TS },
            { name: 'Remove me', timestamp: FIXED_TS },
        ];
        mockStore({ tasks });
        render(<TasksWidget />);

        const buttons = screen.getAllByRole('button', { name: /remove task/i });
        fireEvent.click(buttons[1]); // remove "Remove me"

        await waitFor(() => expect(mockRemoveTask).toHaveBeenCalledTimes(1));
        const [remainingTasks] = mockRemoveTask.mock.calls[0];
        expect(remainingTasks).toHaveLength(1);
        expect(remainingTasks[0].name).toBe('Keep me');
    });
});

// ── User assignee dropdown ────────────────────────────────────────────────────
describe('TasksWidget — assignee dropdown', () => {
    it('shows assignee selector when users list is not empty', () => {
        mockStore({ users: [{ id: 2, display_name: 'Bob' }] });
        render(<TasksWidget />);

        expect(screen.getByRole('combobox')).toBeInTheDocument();
    });

    it('does NOT show assignee selector when users list is empty', () => {
        mockStore({ users: [] });
        render(<TasksWidget />);

        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });

    it('assignee selector includes a "My Tasks" default option', () => {
        mockStore({ users: [{ id: 1, display_name: 'Alice' }] });
        render(<TasksWidget />);

        expect(screen.getByRole('option', { name: /my tasks/i })).toBeInTheDocument();
    });
});
