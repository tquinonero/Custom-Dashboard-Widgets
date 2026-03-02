/**
 * Tests for calculateTimeAgo utility (Part 3.3).
 */
import { calculateTimeAgo } from '../../../src/utils/timeAgo';

describe('calculateTimeAgo()', () => {
    // Pin "now" to a fixed Unix timestamp (seconds)
    const FIXED_NOW_S = 1_700_000_000; // ~Nov 2023

    beforeEach(() => {
        jest.useFakeTimers();
        jest.setSystemTime(FIXED_NOW_S * 1000); // Date.now() in ms
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('10 seconds ago returns "10 seconds"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 10)).toBe('10 seconds');
    });

    it('59 seconds ago returns "59 seconds"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 59)).toBe('59 seconds');
    });

    it('90 seconds ago (1.5 min) returns "1 minutes"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 90)).toBe('1 minutes');
    });

    it('60 seconds ago returns "1 minutes"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 60)).toBe('1 minutes');
    });

    it('2 hours ago returns "2 hours"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 2 * 3600)).toBe('2 hours');
    });

    it('25 hours ago returns "1 days"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 25 * 3600)).toBe('1 days');
    });

    it('48 hours ago returns "2 days"', () => {
        expect(calculateTimeAgo(FIXED_NOW_S - 48 * 3600)).toBe('2 days');
    });

    it('timestamp=0 does not throw and returns a valid string', () => {
        expect(() => calculateTimeAgo(0)).not.toThrow();
        const result = calculateTimeAgo(0);
        expect(typeof result).toBe('string');
        expect(result.length).toBeGreaterThan(0);
    });
});
