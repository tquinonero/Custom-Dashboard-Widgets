/**
 * Tests for CDW Redux store async actions (Part 3.2).
 */
import { actions } from '../../../src/data/store';
import apiFetch from '@wordpress/api-fetch';

// Each test gets a fresh mock dispatch that records plain action objects.
// Thunks (functions) dispatched are stored as-is so we can assert on them.
function makeMockDispatch() {
    const dispatched = [];
    const dispatch = jest.fn((action) => {
        dispatched.push(action);
        return Promise.resolve();
    });
    return { dispatch, dispatched };
}

describe('fetchStats()', () => {
    afterEach(() => jest.clearAllMocks());

    it('calls apiFetch with /cdw/v1/stats', async () => {
        apiFetch.mockResolvedValue({ posts: 5 });
        const { dispatch } = makeMockDispatch();
        await actions.fetchStats()({ dispatch });
        expect(apiFetch).toHaveBeenCalledWith({ path: '/cdw/v1/stats' });
    });

    it('dispatches setStats with response on success', async () => {
        const statsData = { posts: 5, pages: 2 };
        apiFetch.mockResolvedValue(statsData);
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.fetchStats()({ dispatch });
        expect(dispatched).toContainEqual({ type: 'SET_STATS', stats: statsData });
    });

    it('dispatches setLoading stats=false in finally after success', async () => {
        apiFetch.mockResolvedValue({});
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.fetchStats()({ dispatch });
        const last = dispatched[dispatched.length - 1];
        expect(last).toEqual({ type: 'SET_LOADING', key: 'stats', isLoading: false });
    });

    it('dispatches setError on failure and still sets loading to false', async () => {
        apiFetch.mockRejectedValue(new Error('Network error'));
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.fetchStats()({ dispatch });
        expect(dispatched).toContainEqual({ type: 'SET_ERROR', key: 'stats', error: 'Network error' });
        const last = dispatched[dispatched.length - 1];
        expect(last).toEqual({ type: 'SET_LOADING', key: 'stats', isLoading: false });
    });
});

describe('fetchUpdates()', () => {
    afterEach(() => jest.clearAllMocks());

    it('calls apiFetch with /cdw/v1/updates', async () => {
        apiFetch.mockResolvedValue({ core: {}, plugins: [], themes: [] });
        const { dispatch } = makeMockDispatch();
        await actions.fetchUpdates()({ dispatch });
        expect(apiFetch).toHaveBeenCalledWith({ path: '/cdw/v1/updates' });
    });

    it('dispatches setUpdates with response on success', async () => {
        const updatesData = { core: { count: 1, available: true }, plugins: [], themes: [] };
        apiFetch.mockResolvedValue(updatesData);
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.fetchUpdates()({ dispatch });
        expect(dispatched).toContainEqual({ type: 'SET_UPDATES', updates: updatesData });
    });
});

describe('addTask()', () => {
    afterEach(() => jest.clearAllMocks());

    it('dispatches setTasks with response.tasks when assigneeId is null', async () => {
        const returnedTasks = [{ name: 'Task 1', timestamp: 1234 }];
        apiFetch.mockResolvedValue({ tasks: returnedTasks });
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.addTask([{ name: 'Task 1', timestamp: 1234 }], null)({ dispatch });
        expect(dispatched).toContainEqual({ type: 'SET_TASKS', tasks: returnedTasks });
    });

    it('does NOT dispatch setTasks when assigneeId is truthy (5)', async () => {
        apiFetch.mockResolvedValue({ tasks: [{ name: 'Other task' }] });
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.addTask([{ name: 'Task 1' }], 5)({ dispatch });
        expect(dispatched).not.toContainEqual(expect.objectContaining({ type: 'SET_TASKS' }));
    });

    it('re-throws error on failure (not swallowed)', async () => {
        apiFetch.mockRejectedValue(new Error('Server error'));
        const { dispatch } = makeMockDispatch();
        await expect(
            actions.addTask([{ name: 'Task' }], null)({ dispatch })
        ).rejects.toThrow('Server error');
    });
});

describe('removeTask()', () => {
    afterEach(() => jest.clearAllMocks());

    it('always dispatches setTasks with response.tasks', async () => {
        const tasks = [{ name: 'Remaining', timestamp: 9999 }];
        apiFetch.mockResolvedValue({ tasks });
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.removeTask(tasks)({ dispatch });
        expect(dispatched).toContainEqual({ type: 'SET_TASKS', tasks });
    });
});

describe('saveSettings()', () => {
    afterEach(() => jest.clearAllMocks());

    it('on success, dispatches fetchSettings thunk (re-fetch)', async () => {
        apiFetch.mockResolvedValue({ success: true });
        const { dispatch, dispatched } = makeMockDispatch();
        await actions.saveSettings({ email: 'a@b.com' })({ dispatch });
        // fetchSettings() thunk is a function dispatched to the store
        const thunkCalls = dispatched.filter((a) => typeof a === 'function');
        expect(thunkCalls.length).toBeGreaterThanOrEqual(1);
    });

    it('re-throws error on failure', async () => {
        apiFetch.mockRejectedValue(new Error('Save failed'));
        const { dispatch } = makeMockDispatch();
        await expect(
            actions.saveSettings({ email: 'bad' })({ dispatch })
        ).rejects.toThrow('Save failed');
    });
});
