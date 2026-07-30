import { setupServer } from 'msw/node';
import { handlers } from './handlers';

// Shared MSW server for the Node/jsdom test environment.
export const server = setupServer(...handlers);
