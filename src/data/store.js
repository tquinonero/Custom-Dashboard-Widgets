import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const DEFAULT_STATE = {
    stats: {
        posts: 0,
        pages: 0,
        comments: 0,
        users: 0,
        media: 0,
        categories: 0,
        tags: 0,
        plugins: 0,
        themes: 0,
    },
    tasks: [],
    posts: [],
    media: [],
    users: [],
    updates: { core: { count: 0, available: false }, plugins: [], themes: [] },
    settings: {
        email: '',
        docs_url: '',
        font_size: '',
        bg_color: '',
        header_bg_color: '',
        header_text_color: '',
        cli_enabled: true,
        remove_default_widgets: true,
        delete_on_uninstall: true,
        mcp_public: false,
    },
    isLoading: {
        stats: false,
        tasks: false,
        posts: false,
        media: false,
        users: false,
        updates: false,
        settings: false,
    },
    errors: {},
};

const actions = {
    setStats: (stats) => ({ type: 'SET_STATS', stats }),
    setTasks: (tasks) => ({ type: 'SET_TASKS', tasks }),
    setPosts: (posts) => ({ type: 'SET_POSTS', posts }),
    setMedia: (media) => ({ type: 'SET_MEDIA', media }),
    setUsers: (users) => ({ type: 'SET_USERS', users }),
    setUpdates: (updates) => ({ type: 'SET_UPDATES', updates }),
    setSettings: (settings) => ({ type: 'SET_SETTINGS', settings }),
    setLoading: (key, isLoading) => ({ type: 'SET_LOADING', key, isLoading }),
    setError: (key, error) => ({ type: 'SET_ERROR', key, error }),

    fetchStats: () => async ({ dispatch }) => {
        dispatch(actions.setLoading('stats', true));
        try {
            const stats = await apiFetch({ path: '/cdw/v1/stats' });
            dispatch(actions.setStats(stats));
        } catch (error) {
            dispatch(actions.setError('stats', error.message));
        } finally {
            dispatch(actions.setLoading('stats', false));
        }
    },

    fetchTasks: () => async ({ dispatch }) => {
        dispatch(actions.setLoading('tasks', true));
        try {
            const tasks = await apiFetch({ path: '/cdw/v1/tasks' });
            dispatch(actions.setTasks(tasks));
        } catch (error) {
            dispatch(actions.setError('tasks', error.message));
        } finally {
            dispatch(actions.setLoading('tasks', false));
        }
    },

    addTask: (task, assigneeId = null) => async ({ dispatch }) => {
        dispatch(actions.setLoading('tasks', true));
        try {
            const result = await apiFetch({
                path: '/cdw/v1/tasks',
                method: 'POST',
                data: { tasks: task, assignee_id: assigneeId },
            });
            // Only update the store with the returned task list when saving the
            // current user's own tasks. When assigning to another user the server
            // returns *that* user's merged list, which must not replace ours.
            if ( !assigneeId ) {
                dispatch(actions.setTasks(result.tasks));
            }
            return result;
        } catch (error) {
            dispatch(actions.setError('tasks', error.message));
            throw error;
        } finally {
            dispatch(actions.setLoading('tasks', false));
        }
    },

    removeTask: (tasks, assigneeId = null) => async ({ dispatch }) => {
        dispatch(actions.setLoading('tasks', true));
        try {
            const result = await apiFetch({
                path: '/cdw/v1/tasks',
                method: 'POST',
                data: { tasks, assignee_id: assigneeId },
            });
            dispatch(actions.setTasks(result.tasks));
            return result;
        } catch (error) {
            dispatch(actions.setError('tasks', error.message));
            throw error;
        } finally {
            dispatch(actions.setLoading('tasks', false));
        }
    },

    fetchPosts: (perPage = 10) => async ({ dispatch }) => {
        dispatch(actions.setLoading('posts', true));
        try {
            const posts = await apiFetch({ path: `/cdw/v1/posts?per_page=${perPage}` });
            dispatch(actions.setPosts(posts));
        } catch (error) {
            dispatch(actions.setError('posts', error.message));
        } finally {
            dispatch(actions.setLoading('posts', false));
        }
    },

    fetchMedia: (perPage = 10) => async ({ dispatch }) => {
        dispatch(actions.setLoading('media', true));
        try {
            const media = await apiFetch({ path: `/cdw/v1/media?per_page=${perPage}` });
            dispatch(actions.setMedia(media));
        } catch (error) {
            dispatch(actions.setError('media', error.message));
        } finally {
            dispatch(actions.setLoading('media', false));
        }
    },

    fetchUsers: () => async ({ dispatch }) => {
        dispatch(actions.setLoading('users', true));
        try {
            const users = await apiFetch({ path: '/cdw/v1/users' });
            dispatch(actions.setUsers(users));
        } catch (error) {
            dispatch(actions.setError('users', error.message));
        } finally {
            dispatch(actions.setLoading('users', false));
        }
    },

    fetchUpdates: () => async ({ dispatch }) => {
        dispatch(actions.setLoading('updates', true));
        try {
            const updates = await apiFetch({ path: '/cdw/v1/updates' });
            dispatch(actions.setUpdates(updates));
        } catch (error) {
            dispatch(actions.setError('updates', error.message));
        } finally {
            dispatch(actions.setLoading('updates', false));
        }
    },

    fetchSettings: () => async ({ dispatch }) => {
        dispatch(actions.setLoading('settings', true));
        try {
            const settings = await apiFetch({ path: '/cdw/v1/settings' });
            dispatch(actions.setSettings(settings));
        } catch (error) {
            dispatch(actions.setError('settings', error.message));
        } finally {
            dispatch(actions.setLoading('settings', false));
        }
    },

    saveSettings: (settings) => async ({ dispatch }) => {
        dispatch(actions.setLoading('settings', true));
        try {
            await apiFetch({
                path: '/cdw/v1/settings',
                method: 'POST',
                data: settings,
            });
            // Re-fetch from the server so the store reflects the server-validated values
            // rather than the submitted values (server silently drops invalid fields).
            await dispatch(actions.fetchSettings());
        } catch (error) {
            dispatch(actions.setError('settings', error.message));
            throw error;
        } finally {
            dispatch(actions.setLoading('settings', false));
        }
    },
};

const reducer = (state = DEFAULT_STATE, action) => {
    switch (action.type) {
        case 'SET_STATS':
            return { ...state, stats: action.stats };
        case 'SET_TASKS':
            return { ...state, tasks: action.tasks };
        case 'SET_POSTS':
            return { ...state, posts: action.posts };
        case 'SET_MEDIA':
            return { ...state, media: action.media };
        case 'SET_USERS':
            return { ...state, users: action.users };
        case 'SET_UPDATES':
            return { ...state, updates: action.updates };
        case 'SET_SETTINGS':
            return { ...state, settings: action.settings };
        case 'SET_LOADING':
            return {
                ...state,
                isLoading: { ...state.isLoading, [action.key]: action.isLoading },
            };
        case 'SET_ERROR':
            return {
                ...state,
                errors: { ...state.errors, [action.key]: action.error },
            };
        default:
            return state;
    }
};

const selectors = {
    getStats: (state) => state.stats,
    getTasks: (state) => state.tasks,
    getPosts: (state) => state.posts,
    getMedia: (state) => state.media,
    getUsers: (state) => state.users,
    getUpdates: (state) => state.updates,
    getSettings: (state) => state.settings,
    isLoading: (state, key) => state.isLoading[key],
    getError: (state, key) => state.errors[key],
};

const storeConfig = {
    reducer,
    actions,
    selectors,
};

const cdwStore = createReduxStore( 'cdw/store', storeConfig );
register( cdwStore );

export const store = 'cdw/store';
export { actions, selectors, reducer };
