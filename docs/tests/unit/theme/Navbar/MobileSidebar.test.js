import { fireEvent, render } from '@testing-library/react';
import { useNavbarMobileSidebar } from '@docusaurus/theme-common/internal';
import MobileSidebar from '../../../../src/theme/Navbar/MobileSidebar';

jest.mock('@docusaurus/theme-common/internal', () => ({ useNavbarMobileSidebar: jest.fn() }));

// The markup Docusaurus draws around its mobile sidebar.
const PAGE = `
  <div id="__docusaurus">
    <div class="skip"><a href="#content">Skip to main content</a></div>
    <nav class="navbar">
      <div class="navbar__inner"><button class="navbar__toggle">Menu</button></div>
      <div class="navbar-sidebar__backdrop"></div>
      <div class="navbar-sidebar"><a href="/" id="logo">Behat Steps</a><button>Close</button></div>
    </nav>
    <main><a href="/next" id="content">Next</a></main>
  </div>
`;

describe('MobileSidebar', () => {
  let toggle;

  beforeEach(() => {
    toggle = jest.fn();
    document.body.innerHTML = PAGE;
  });

  const renderSidebar = (shown) => {
    useNavbarMobileSidebar.mockReturnValue({ shown, toggle });

    return render(<MobileSidebar />);
  };

  const closeSidebar = (rerender) => {
    useNavbarMobileSidebar.mockReturnValue({ shown: false, toggle });
    rerender(<MobileSidebar />);
  };

  test.each([
    { selector: '.skip', expected: true },
    { selector: '.navbar__inner', expected: true },
    { selector: 'main', expected: true },
    { selector: '.navbar-sidebar__backdrop', expected: false },
    { selector: '.navbar-sidebar', expected: false },
    { selector: '.navbar', expected: false },
  ])('sets inert on $selector to $expected while the sidebar is open', ({ selector, expected }) => {
    renderSidebar(true);

    expect(document.querySelector(selector).hasAttribute('inert')).toBe(expected);
  });

  test('moves focus into the open sidebar', () => {
    renderSidebar(true);

    expect(document.activeElement).toBe(document.getElementById('logo'));
  });

  test.each([
    { key: 'Escape', expected: 1 },
    { key: 'Enter', expected: 0 },
  ])('closes on $key: $expected', ({ key, expected }) => {
    renderSidebar(true);

    fireEvent.keyDown(document, { key });

    expect(toggle).toHaveBeenCalledTimes(expected);
  });

  test('restores the page and returns focus to the toggle once closed', () => {
    const { rerender } = renderSidebar(true);

    closeSidebar(rerender);

    expect(document.querySelectorAll('[inert]')).toHaveLength(0);
    expect(document.activeElement).toBe(document.querySelector('.navbar__toggle'));

    fireEvent.keyDown(document, { key: 'Escape' });

    expect(toggle).not.toHaveBeenCalled();
  });

  test('leaves focus that a page change moved', () => {
    const { rerender } = renderSidebar(true);
    const content = document.getElementById('content');

    content.focus();
    closeSidebar(rerender);

    expect(document.activeElement).toBe(content);
  });

  test('keeps elements inert that were inert before it opened', () => {
    document.querySelector('main').setAttribute('inert', '');

    const { rerender } = renderSidebar(true);

    closeSidebar(rerender);

    expect(document.querySelector('main')).toHaveAttribute('inert');
    expect(document.querySelector('.navbar__inner')).not.toHaveAttribute('inert');
  });

  test('leaves the page alone while closed', () => {
    renderSidebar(false);

    fireEvent.keyDown(document, { key: 'Escape' });

    expect(document.querySelectorAll('[inert]')).toHaveLength(0);
    expect(document.activeElement).toBe(document.body);
    expect(toggle).not.toHaveBeenCalled();
  });

  test('leaves the page alone without a sidebar', () => {
    document.querySelector('.navbar-sidebar').remove();

    renderSidebar(true);

    expect(document.querySelectorAll('[inert]')).toHaveLength(0);
  });

  describe('while the sidebar is still hidden', () => {
    let frames;

    beforeEach(() => {
      frames = [];
      jest.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => frames.push(callback));
      jest.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {});
      document.querySelector('.navbar-sidebar').style.visibility = 'hidden';
    });

    afterEach(() => {
      jest.restoreAllMocks();
    });

    const runNextFrame = () => frames.shift()();

    test('moves focus into it once it turns visible', () => {
      renderSidebar(true);
      runNextFrame();

      expect(document.activeElement).toBe(document.body);

      document.querySelector('.navbar-sidebar').style.visibility = 'visible';
      runNextFrame();

      expect(document.activeElement).toBe(document.getElementById('logo'));
      expect(frames).toHaveLength(0);
    });

    test('stops waiting after 60 frames', () => {
      renderSidebar(true);

      let count = 0;

      while (frames.length > 0) {
        runNextFrame();
        count++;
      }

      expect(count).toBe(60);
      expect(document.activeElement).toBe(document.body);
    });

    test('stops waiting once closed', () => {
      const { rerender } = renderSidebar(true);

      closeSidebar(rerender);

      expect(window.cancelAnimationFrame).toHaveBeenCalledWith(1);
    });
  });
});
