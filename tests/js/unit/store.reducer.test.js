/**
 * Tests for the CDW Redux store reducer (Part 3.1).
 */
import { reducer } from '../../../src/data/store';

// Default state snapshot (mirrors DEFAULT_STATE in store.js)
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

describe('CDW Store Reducer', () => {
    it('SET_STATS replaces stats and leaves other keys untouched', () => {
        const newStats = { posts: 5, pages: 3, comments: 1, users: 2, media: 4, categories: 2, tags: 1, plugins: 7, themes: 3 };
        const state = reducer(DEFAULT_STATE, { type: 'SET_STATS', stats: newStats });
        expect(state.stats).toEqual(newStats);
        expect(state.tasks).toEqual(DEFAULT_STATE.tasks);
        expect(state.posts).toEqual(DEFAULT_STATE.posts);
    });

    it('SET_TASKS replaces tasks array', () => {
        const tasks = [{ name: 'Do something', timestamp: 1234567890, created_by: 1 }];
        const state = reducer(DEFAULT_STATE, { type: 'SET_TASKS', tasks });
        expect(state.tasks).toEqual(tasks);
        expect(state.stats).toEqual(DEFAULT_STATE.stats);
    });

    it('SET_POSTS replaces posts', () => {
        const posts = [{ id: 1, title: 'Hello', permalink: 'https://example.com/hello' }];
        const state = reducer(DEFAULT_STATE, { type: 'SET_POSTS', posts });
        expect(state.posts).toEqual(posts);
    });

    it('SET_MEDIA replaces media', () => {
        const media = [{ id: 10, url: 'https://example.com/img.png' }];
        const state = reducer(DEFAULT_STATE, { type: 'SET_MEDIA', media });
        expect(state.media).toEqual(media);
    });

    it('SET_USERS replaces users', () => {
        const users = [{ id: 1, name: 'Admin' }];
        const state = reducer(DEFAULT_STATE, { type: 'SET_USERS', users });
        expect(state.users).toEqual(users);
    });

    it('SET_UPDATES replaces updates with core/plugins/themes shape', () => {
        const updates = {
            core: { count: 1, available: true },
            plugins: [{ file: 'foo/foo.php', name: 'Foo', version: '1.0', new_version: '1.1' }],
            themes: [],
        };
        const state = reducer(DEFAULT_STATE, { type: 'SET_UPDATES', updates });
        expect(state.updates).toEqual(updates);
    });

    it('SET_SETTINGS replaces settings', () => {
        const settings = { email: 'test@example.com', docs_url: 'https://docs.example.com' };
        const state = reducer(DEFAULT_STATE, { type: 'SET_SETTINGS', settings });
        expect(state.settings).toEqual(settings);
    });

    it('SET_LOADING with key=stats,value=true sets isLoading.stats to true', () => {
        const state = reducer(DEFAULT_STATE, { type: 'SET_LOADING', key: 'stats', isLoading: true });
        expect(state.isLoading.stats).toBe(true);
        // Other loading keys stay unchanged
        expect(state.isLoading.tasks).toBe(false);
        expect(state.isLoading.posts).toBe(false);
    });

    it('SET_LOADING with key=stats,value=false sets isLoading.stats to false', () => {
        const withLoading = { ...DEFAULT_STATE, isLoading: { ...DEFAULT_STATE.isLoading, stats: true } };
        const state = reducer(withLoading, { type: 'SET_LOADING', key: 'stats', isLoading: false });
        expect(state.isLoading.stats).toBe(false);
    });

    it('SET_ERROR with key=stats sets errors.stats and leaves other errors unchanged', () => {
        const withErr = { ...DEFAULT_STATE, errors: { tasks: 'old error' } };
        const state = reducer(withErr, { type: 'SET_ERROR', key: 'stats', error: 'something broke' });
        expect(state.errors.stats).toBe('something broke');
        expect(state.errors.tasks).toBe('old error');
    });

    it('Unknown action type returns same state reference unchanged', () => {
        const state = reducer(DEFAULT_STATE, { type: 'UNKNOWN_ACTION' });
        expect(state).toBe(DEFAULT_STATE);
    });
});
