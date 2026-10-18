// @ts-check

module.exports = {
  testEnvironment: 'jsdom',
  testMatch: [
    '<rootDir>/tests/unit/**/*.{test,spec}.{js,jsx,ts,tsx}',
    '<rootDir>/src/**/*.{test,spec}.{js,jsx,ts,tsx}'
  ],
  setupFilesAfterEnv: ['<rootDir>/tests/jest-setup.js'],
  moduleNameMapper: {
    '^@site/(.*)$': '<rootDir>/$1',
    '^@theme-original/(.*)$': '<rootDir>/tests/__mocks__/theme-original/$1.js'
  },
  collectCoverageFrom: [
    'src/*.js',
    'src/components/**/*.{js,jsx}',
    'src/remark/**/*.js',
    'src/theme/**/*.{js,jsx}',
    '!src/**/*.test.{js,jsx}',
    '!src/**/*.spec.{js,jsx}'
  ],
  transform: {
    '^.+\\.(js|jsx)$': ['babel-jest', {
      presets: [
        ['@babel/preset-env', { targets: { node: 'current' } }],
        ['@babel/preset-react', { runtime: 'automatic' }]
      ]
    }]
  },
  transformIgnorePatterns: [
    'node_modules/(?!(@testing-library|@babel/runtime)/)'
  ],
  testTimeout: 10000,
  coverageReporters: ['text', 'lcov', 'html', ['cobertura', { file: 'cobertura.xml' }]],
  coverageDirectory: '.logs/coverage'
};
