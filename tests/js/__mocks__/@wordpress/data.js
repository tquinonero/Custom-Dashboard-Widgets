module.exports = {
  useSelect: jest.fn((fn) => fn({ select: jest.fn() })),
  useDispatch: jest.fn(() => ({})),
  createReduxStore: jest.fn(),
  register: jest.fn(),
  select: jest.fn(),
  dispatch: jest.fn(),
};
