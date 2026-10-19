import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import CopyCommand, { copyText, segmentsOf, tokenize } from '../../../src/components/CopyCommand';

const INSTALL = 'composer require --dev drevops/behat-steps:^4';

describe('tokenize', () => {
  const kinds = (command) => tokenize(command).filter((token) => token.kind !== 'space').map(({ text, kind }) => `${kind}:${text}`);

  test.each([
    {
      name: 'a program and its argument',
      command: 'vendor/bin/behat features',
      expected: ['program:vendor/bin/behat', 'argument:features'],
    },
    {
      name: 'a redirect, whose target is an argument',
      command: 'ls -la > out.txt',
      expected: ['program:ls', 'flag:-la', 'operator:>', 'argument:out.txt'],
    },
    {
      name: 'commands joined by operators',
      command: 'a && b || c | d ; e',
      expected: ['program:a', 'operator:&&', 'program:b', 'operator:||', 'program:c', 'operator:|', 'program:d', 'operator:;', 'program:e'],
    },
    {
      name: 'the install command',
      command: INSTALL,
      expected: ['program:composer', 'argument:require', 'flag:--dev', 'argument:drevops/behat-steps:^4'],
    },
  ])('tags $name', ({ command, expected }) => {
    expect(kinds(command)).toEqual(expected);
  });

  test('keeps the spaces between the words', () => {
    const tokens = tokenize('vendor/bin/behat  features');

    expect(tokens.map((token) => token.text).join('')).toBe('vendor/bin/behat  features');
    expect(tokens[1]).toEqual({ text: '  ', kind: 'space' });
  });
});

describe('segmentsOf', () => {
  test.each([
    { word: 'drevops/behat-steps:^4', expected: ['drevops/', 'behat-steps:^4'] },
    { word: 'vendor/bin/behat', expected: ['vendor/', 'bin/', 'behat'] },
    { word: 'https://behat-steps.drevops.com/install', expected: ['https://', 'behat-steps.drevops.com/', 'install'] },
    { word: '/var//www/', expected: ['/', 'var//', 'www/'] },
    { word: 'behat.php', expected: ['behat.php'] },
  ])('splits $word after each run of slashes', ({ word, expected }) => {
    expect(segmentsOf(word)).toEqual(expected);
  });
});

describe('copyText', () => {
  let field;

  beforeEach(() => {
    const create = document.createElement.bind(document);

    jest.spyOn(document, 'createElement').mockImplementation((tag) => {
      field = create(tag);

      return field;
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete navigator.clipboard;
    delete document.execCommand;
  });

  test('writes through the Clipboard API', async () => {
    navigator.clipboard = { writeText: jest.fn(() => Promise.resolve()) };

    await expect(copyText(INSTALL)).resolves.toBe(true);
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(INSTALL);
  });

  test.each([
    { name: 'the Clipboard API is missing', clipboard: undefined },
    { name: 'the Clipboard API refuses', clipboard: { writeText: () => Promise.reject(new Error('Denied')) } },
  ])('copies a selection when $name', async ({ clipboard }) => {
    navigator.clipboard = clipboard;
    document.execCommand = jest.fn(() => {
      expect(field.value).toBe(INSTALL);
      expect([field.selectionStart, field.selectionEnd]).toEqual([0, INSTALL.length]);

      return true;
    });

    await expect(copyText(INSTALL)).resolves.toBe(true);
    expect(document.execCommand).toHaveBeenCalledWith('copy');
    expect(field.isConnected).toBe(false);
  });

  test.each([
    { name: 'refuses', execCommand: () => false },
    { name: 'throws', execCommand: () => { throw new Error('Unsupported'); } },
  ])('reports failure when the selection copy $name', async ({ execCommand }) => {
    document.execCommand = jest.fn(execCommand);

    await expect(copyText(INSTALL)).resolves.toBe(false);
    expect(field.isConnected).toBe(false);
  });
});

describe('CopyCommand', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    navigator.clipboard = { writeText: jest.fn(() => Promise.resolve()) };
  });

  afterEach(() => {
    // Unmounts first, so the component clears its timer under the fake timers.
    cleanup();
    jest.useRealTimers();
    delete navigator.clipboard;
    delete document.execCommand;
  });

  const copy = async (name) => {
    fireEvent.click(screen.getByRole('button', { name }));

    // Lets the clipboard promise settle.
    await act(async () => {});
  };

  test('shows the command after a prompt that is not read out', () => {
    const { container } = render(<CopyCommand command={INSTALL} name="install command" />);
    const code = container.querySelector('code');

    expect(code.textContent).toBe(`$ ${INSTALL}`);
    expect(code.querySelector('[aria-hidden="true"]').textContent).toBe('$ ');
  });

  test.each([
    { path: 'drevops/behat-steps:^4', expected: '<span class="segment">drevops/</span><wbr><span class="segment">behat-steps:^4</span>' },
    { path: 'vendor/bin/behat', expected: '<span class="segment">vendor/</span><wbr><span class="segment">bin/</span><wbr><span class="segment">behat</span>' },
    { path: 'behat.php', expected: '<span class="segment">behat.php</span>' },
  ])('breaks $path only after a slash', ({ path, expected }) => {
    const { container } = render(<CopyCommand command={`ls ${path}`} name="command" />);
    const argument = container.querySelector('code .argument');

    expect(argument.innerHTML).toBe(expected);
  });

  test('keeps the spaces between the words as plain text', () => {
    const { container } = render(<CopyCommand command="ls   -la" name="command" />);

    expect(container.querySelector('code .space').innerHTML).toBe('   ');
  });

  test('copies the command and says so until the time runs out', async () => {
    render(<CopyCommand command={INSTALL} name="install command" />);

    await copy('Copy the install command');

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(INSTALL);
    expect(screen.getByRole('button', { name: 'Copied the install command' })).toHaveClass('copied');
    expect(screen.getByRole('status')).toHaveTextContent('Copied the install command to the clipboard.');

    act(() => {
      jest.advanceTimersByTime(1999);
    });

    expect(screen.getByRole('button', { name: 'Copied the install command' })).toBeInTheDocument();

    act(() => {
      jest.advanceTimersByTime(1);
    });

    expect(screen.getByRole('button', { name: 'Copy the install command' })).not.toHaveClass('copied');
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  test('restarts the time on a second copy', async () => {
    render(<CopyCommand command="vendor/bin/behat" name="Behat command" />);

    await copy('Copy the Behat command');

    act(() => {
      jest.advanceTimersByTime(1500);
    });

    await copy('Copied the Behat command');

    act(() => {
      jest.advanceTimersByTime(1500);
    });

    expect(screen.getByRole('button', { name: 'Copied the Behat command' })).toBeInTheDocument();
  });

  test('claims nothing when the copy fails', async () => {
    navigator.clipboard = { writeText: () => Promise.reject(new Error('Denied')) };
    document.execCommand = jest.fn(() => false);

    render(<CopyCommand command="vendor/bin/behat" name="Behat command" />);

    await copy('Copy the Behat command');

    expect(screen.getByRole('button', { name: 'Copy the Behat command' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  test.each([
    { name: 'shows its label', compact: false, expected: 'label' },
    { name: 'hides its label in the compact form', compact: true, expected: 'visually-hidden' },
  ])('$name', ({ compact, expected }) => {
    const { container } = render(<CopyCommand command="vendor/bin/behat" name="Behat command" compact={compact} className="extra" />);

    expect(screen.getByText('Copy')).toHaveAttribute('class', expected);
    expect(container.firstChild).toHaveClass('command', 'extra');
    expect(container.firstChild.classList.contains('compact')).toBe(compact);
  });
});
