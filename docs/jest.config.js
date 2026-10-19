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
    '^@docusaurus/(Link|useGlobalData)$': '<rootDir>/tests/__mocks__/docusaurus/$1.js',
    '^@theme/(.*)$': '<rootDir>/tests/__mocks__/theme/$1.js',
    '^@theme-original/(.*)$': '<rootDir>/tests/__mocks__/theme-original/$1.js',
    '\\.css$': '<rootDir>/tests/__mocks__/styleModule.js'
  },
  collectCoverageFrom: [
    'src/*.js',
    'src/components/**/*.{js,jsx}',
    'src/pages/**/*.{js,jsx}',
    'src/remark/**/*.js',
    'src/theme/**/*.{js,jsx}',
    '!src/**/*.test.{js,jsx}',
    '!src/**/*.spec.{js,jsx}'
  ],
  transform: {
    '^.+\\.(js|jsx)$': ['babel-jest', {
      // Under Jest, the Docusaurus preset compiles a spread of a Set to
      // '[].concat()', so the tests compile with the presets below only.
      configFile: false,
      babelrc: false,
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
