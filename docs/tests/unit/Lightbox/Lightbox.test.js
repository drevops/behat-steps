import { StrictMode } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import Lightbox, { isImageFile, isSecondClick, LightboxImage, LightboxLink, scrollToKeep, zoomedWidth } from '../../../src/components/Lightbox';

const SRC = '/assets/images/flow.svg#gh-light-mode-only';
const HREF = '/assets/files/flow.svg';
const ALT = 'Data flow: the 4 phases of a run';

const VIEW = { left: 0, top: 0, width: 1280, height: 828 };
const FITTED = { left: 140, top: 64, width: 1000, height: 700 };
const ZOOMED = { left: 16, top: 64, width: 2400, height: 1680 };

// jsdom has no PointerEvent, so a pointer event is a mouse event that carries
// the id and the type of its pointer.
class PointerEvent extends MouseEvent {
  constructor(type, init = {}) {
    super(type, init);
    this.pointerId = init.pointerId ?? 1;
    this.pointerType = init.pointerType ?? 'mouse';
  }
}

// jsdom has no modal dialogs, so opening one sets its 'open' attribute and
// closing one removes it and fires 'close', as a browser does.
const stubBrowser = () => {
  window.PointerEvent = PointerEvent;
  HTMLDialogElement.prototype.showModal = jest.fn(function showModal() {
    this.setAttribute('open', '');
  });
  HTMLDialogElement.prototype.close = jest.fn(function close() {
    this.removeAttribute('open');
    this.dispatchEvent(new Event('close'));
  });
  Element.prototype.setPointerCapture = jest.fn();
};

const unstubBrowser = () => {
  jest.restoreAllMocks();
  delete window.PointerEvent;
  delete HTMLDialogElement.prototype.showModal;
  delete HTMLDialogElement.prototype.close;
  delete Element.prototype.setPointerCapture;
};

const dialog = () => document.querySelector('dialog');

const zoomButton = () => screen.getByRole('button', { name: /^Zoom (in|out)$/ });

// jsdom has no layout, so the view and the image get their boxes here: the
// image reports its fitted box for the zoom, then its zoomed box.
const layOut = ({ fitted = FITTED, zoomed = ZOOMED, naturalWidth = 2400 } = {}) => {
  const image = dialog().querySelector('img');
  const view = zoomButton().parentElement;

  Object.defineProperty(image, 'naturalWidth', { value: naturalWidth });
  jest.spyOn(image, 'getBoundingClientRect').mockReturnValueOnce(fitted).mockReturnValue(zoomed);
  jest.spyOn(view, 'getBoundingClientRect').mockReturnValue(VIEW);

  return { image, view };
};

describe('isImageFile', () => {
  test.each([
    { href: HREF, expected: true },
    { href: 'flow.dark.svg#gh-dark-mode-only', expected: true },
    { href: '/img/screenshot.PNG?version=2', expected: true },
    { href: 'photo.jpeg', expected: true },
    { href: 'photo.jpg', expected: true },
    { href: 'animation.gif', expected: true },
    { href: 'picture.webp', expected: true },
    { href: 'picture.avif', expected: true },
    { href: '/guides/configuration', expected: false },
    { href: '/guides/configuration#backend-resolution', expected: false },
    { href: 'https://github.com/drevops/behat-steps', expected: false },
    { href: '/assets/files/flow.svg.zip', expected: false },
    { href: '/svg/flow', expected: false },
    { href: undefined, expected: false },
  ])('tells whether $href is an image file', ({ href, expected }) => {
    expect(isImageFile(href)).toBe(expected);
  });
});

describe('zoomedWidth', () => {
  test.each([
    { name: 'the natural width, when it is more than twice the fitted width', naturalWidth: 2400, fittedWidth: 1000, expected: 2400 },
    { name: 'twice the fitted width, when the natural width is less', naturalWidth: 1200, fittedWidth: 1000, expected: 2000 },
    { name: 'twice the fitted width, when the natural width is unknown', naturalWidth: 0, fittedWidth: 300, expected: 600 },
  ])('zooms to $name', ({ naturalWidth, fittedWidth, expected }) => {
    expect(zoomedWidth(naturalWidth, fittedWidth)).toBe(expected);
  });
});

describe('scrollToKeep', () => {
  test.each([
    {
      name: 'an unscrolled view',
      anchor: { point: { x: 640, y: 414 }, fraction: { x: 0.5, y: 0.5 } },
      view: { scrollLeft: 0, scrollTop: 0 },
      expected: { left: 576, top: 490 },
    },
    {
      name: 'a scrolled view',
      anchor: { point: { x: 100, y: 200 }, fraction: { x: 0.25, y: 0.75 } },
      view: { scrollLeft: 300, scrollTop: 100 },
      expected: { left: 816, top: 1224 },
    },
  ])('brings the point back under the pointer in $name', ({ anchor, view, expected }) => {
    expect(scrollToKeep(anchor, ZOOMED, view)).toEqual(expected);
  });
});

describe('isSecondClick', () => {
  const FIRST = { x: 100, y: 100, time: 1000 };

  test.each([
    { name: 'a click at the same place right after it', second: { x: 100, y: 100, time: 1200 }, expected: true },
    { name: 'a tap a fingertip away just inside the interval', second: { x: 130, y: 120, time: 1499 }, expected: true },
    { name: 'a click at the same place once the interval is over', second: { x: 100, y: 100, time: 1500 }, expected: false },
    { name: 'a click elsewhere right after it', second: { x: 140, y: 100, time: 1200 }, expected: false },
    { name: 'a click from the keyboard right after it', second: null, expected: false },
  ])('tells whether $name ends a double-click', ({ second, expected }) => {
    expect(isSecondClick(FIRST, second)).toBe(expected);
  });

  test('finds no double-click after a click from the keyboard', () => {
    expect(isSecondClick(null, FIRST)).toBe(false);
  });
});

describe('Lightbox', () => {
  let onClose;

  beforeEach(() => {
    stubBrowser();
    onClose = jest.fn();
  });

  afterEach(unstubBrowser);

  const open = (props = {}) => render(<Lightbox src={SRC} alt={ALT} onClose={onClose} {...props} />);

  const zoomAt = (x, y) => fireEvent.click(dialog().querySelector('img'), { detail: 1, clientX: x, clientY: y });

  test('opens a modal dialog on the page, named after the image', () => {
    const { container } = open();

    expect(HTMLDialogElement.prototype.showModal).toHaveBeenCalledTimes(1);
    expect(dialog().parentElement).toBe(document.body);
    expect(container).toBeEmptyDOMElement();
    expect(screen.getByRole('dialog', { name: ALT })).toHaveAttribute('open');
    expect(dialog().querySelector('img')).toHaveAttribute('src', SRC);
    expect(dialog().querySelector('img')).toHaveAttribute('alt', ALT);
  });

  test('moves focus to the image, which zooms it', () => {
    open();

    expect(screen.getByRole('button', { name: 'Zoom in' })).toHaveFocus();
  });

  test('opens the dialog once when its effects run twice', () => {
    render(
      <StrictMode>
        <Lightbox src={SRC} alt={ALT} onClose={onClose} />
      </StrictMode>,
    );

    expect(HTMLDialogElement.prototype.showModal).toHaveBeenCalledTimes(1);
  });

  test.each([
    { name: 'links the image in a new tab when it has a link target', href: HREF, expected: 1 },
    { name: 'offers no new tab without a link target', href: undefined, expected: 0 },
  ])('$name', ({ href, expected }) => {
    open({ href });

    const links = screen.queryAllByRole('link', { name: 'Open the image in a new tab' });

    expect(links).toHaveLength(expected);
    links.forEach((link) => {
      expect(link).toHaveAttribute('href', HREF);
      expect(link).toHaveAttribute('target', '_blank');
      expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    });
  });

  test.each([
    { name: 'the close button', act: () => fireEvent.click(screen.getByRole('button', { name: 'Close' })) },
    { name: 'a click beside the image', act: () => fireEvent.click(zoomButton().parentElement) },
    { name: 'the browser closing it, as Escape does', act: () => dialog().close() },
  ])('closes on $name', ({ act }) => {
    open();

    act();

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(dialog()).not.toHaveAttribute('open');
  });

  test('zooms the image around the point under the pointer and back', () => {
    open();

    const { image, view } = layOut();

    zoomAt(640, 414);

    expect(image).toHaveStyle({ width: '2400px' });
    expect(zoomButton()).toHaveAccessibleName('Zoom out');
    expect([view.scrollLeft, view.scrollTop]).toEqual([576, 490]);
    expect(onClose).not.toHaveBeenCalled();

    zoomAt(640, 414);

    expect(image.style.width).toBe('');
    expect(zoomButton()).toHaveAccessibleName('Zoom in');
  });

  test('zooms around the middle of the view from the keyboard', () => {
    open();

    const { view } = layOut({ fitted: { left: 0, top: 0, width: 1280, height: 828 }, zoomed: { left: 16, top: 64, width: 2560, height: 1656 } });

    fireEvent.click(zoomButton());

    expect([view.scrollLeft, view.scrollTop]).toEqual([656, 478]);
  });

  test('keeps the point inside the image when the pointer is beside it', () => {
    open();

    const { view } = layOut();

    zoomAt(1200, 10);

    expect([view.scrollLeft, view.scrollTop]).toEqual([1216, 54]);
  });

  test('ignores the second half of the double-click that opened it', () => {
    jest.spyOn(performance, 'now').mockReturnValue(1200);
    open({ openedBy: { x: 640, y: 414, time: 1000 } });

    const { view } = layOut();

    fireEvent.click(view, { detail: 1, clientX: 640, clientY: 414 });
    zoomAt(640, 414);

    expect(onClose).not.toHaveBeenCalled();
    expect(zoomButton()).toHaveAccessibleName('Zoom in');

    fireEvent.click(zoomButton());

    expect(zoomButton()).toHaveAccessibleName('Zoom out');

    fireEvent.click(view, { detail: 1, clientX: 8, clientY: 790 });

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('ignores a zoom before the image has a size', () => {
    open();

    const { image } = layOut({ fitted: { left: 640, top: 414, width: 0, height: 0 }, naturalWidth: 0 });

    zoomAt(640, 414);

    expect(image.style.width).toBe('');
    expect(zoomButton()).toHaveAccessibleName('Zoom in');
  });

  describe('dragging', () => {
    const zoomIn = () => {
      open();

      const parts = layOut();

      zoomAt(640, 414);

      return parts;
    };

    const press = (target, init = {}) => fireEvent.pointerDown(target, { button: 0, buttons: 1, clientX: 600, clientY: 400, ...init });

    const moveTo = (target, x, y, buttons = 1) => fireEvent.pointerMove(target, { buttons, clientX: x, clientY: y });

    test('pans a zoomed image with the mouse once it moves past the threshold', () => {
      const { image, view } = zoomIn();

      press(image);
      moveTo(view, 602, 401);

      expect([view.scrollLeft, view.scrollTop]).toEqual([576, 490]);
      expect(Element.prototype.setPointerCapture).not.toHaveBeenCalled();
      expect(view).not.toHaveClass('dragging');

      moveTo(view, 500, 350);

      expect([view.scrollLeft, view.scrollTop]).toEqual([676, 540]);
      expect(Element.prototype.setPointerCapture).toHaveBeenCalledWith(1);
      expect(view).toHaveClass('dragging');

      moveTo(view, 599, 399);

      expect([view.scrollLeft, view.scrollTop]).toEqual([577, 491]);
      expect(Element.prototype.setPointerCapture).toHaveBeenCalledTimes(1);

      fireEvent.pointerUp(view);

      expect(view).not.toHaveClass('dragging');
    });

    test('neither zooms nor closes on the click that ends a drag', () => {
      const { image, view } = zoomIn();

      press(image);
      moveTo(view, 500, 350);
      fireEvent.pointerUp(view);
      fireEvent.click(image, { detail: 1, clientX: 500, clientY: 350 });

      expect(zoomButton()).toHaveAccessibleName('Zoom out');
      expect(onClose).not.toHaveBeenCalled();

      press(view);
      moveTo(view, 500, 350);
      fireEvent.pointerUp(view);
      fireEvent.click(view, { detail: 1 });

      expect(onClose).not.toHaveBeenCalled();

      press(view);
      fireEvent.pointerUp(view);
      fireEvent.click(view, { detail: 1 });

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    test.each([
      { name: 'a fitted image', zoom: false, init: {} },
      { name: 'a touch', zoom: true, init: { pointerType: 'touch' } },
      { name: 'a pen', zoom: true, init: { pointerType: 'pen' } },
      { name: 'the secondary mouse button', zoom: true, init: { button: 2, buttons: 2 } },
    ])('leaves $name to the browser', ({ zoom, init }) => {
      open();

      const { image, view } = layOut();

      if (zoom) {
        zoomAt(640, 414);
      }

      const before = [view.scrollLeft, view.scrollTop];

      press(image, init);
      moveTo(view, 500, 350);

      expect([view.scrollLeft, view.scrollTop]).toEqual(before);
      expect(Element.prototype.setPointerCapture).not.toHaveBeenCalled();
    });

    test('stops when the button was released outside the view', () => {
      const { image, view } = zoomIn();

      press(image);
      moveTo(view, 590, 395, 0);
      moveTo(view, 500, 350);

      expect([view.scrollLeft, view.scrollTop]).toEqual([576, 490]);
    });

    test('lets the next click through when the browser cancels a drag', () => {
      const { image, view } = zoomIn();

      press(image);
      moveTo(view, 500, 350);
      fireEvent.pointerCancel(view);

      expect(view).not.toHaveClass('dragging');

      fireEvent.click(view, { detail: 1 });

      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });
});

describe('LightboxImage', () => {
  beforeEach(stubBrowser);

  afterEach(unstubBrowser);

  test('opens an image with alt text in the lightbox', () => {
    render(<LightboxImage src={SRC} alt={ALT} width="2371" height="1180" />);

    const opener = screen.getByRole('button', { name: ALT });

    expect(opener).toHaveAttribute('aria-haspopup', 'dialog');
    expect(opener).toHaveClass('opener', 'imageButton');
    expect(opener.querySelector('img')).toHaveAttribute('width', '2371');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    fireEvent.click(opener);

    expect(screen.getByRole('dialog', { name: ALT })).toBeInTheDocument();
    expect(dialog().querySelector('img')).toHaveAttribute('src', SRC);
    expect(screen.queryByRole('link', { name: 'Open the image in a new tab' })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Close' }));

    expect(dialog()).toBeNull();
  });

  test.each([
    { name: 'empty alt text', alt: '' },
    { name: 'no alt text', alt: undefined },
  ])('leaves an image with $name as it is', ({ alt }) => {
    const { container } = render(<LightboxImage src={SRC} alt={alt} />);

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(container.firstChild.tagName).toBe('IMG');
  });

  test('leaves an image inside a link as it is', () => {
    render(
      <LightboxLink href="https://github.com/drevops/behat-steps/actions">
        <LightboxImage src="/img/badge.svg" alt="Test" />
      </LightboxLink>,
    );

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Test' }).firstChild.tagName).toBe('IMG');
  });
});

describe('LightboxLink', () => {
  beforeEach(stubBrowser);

  afterEach(unstubBrowser);

  const imageLink = (children = <LightboxImage src={SRC} alt={ALT} />) => render(<LightboxLink href={HREF} target="_blank">{children}</LightboxLink>);

  test.each([
    { name: 'a link to a page', href: '/guides/configuration', children: 'Configuration' },
    { name: 'a text link to an image file', href: HREF, children: 'light' },
    { name: 'a link to an image file that holds text too', href: HREF, children: [<LightboxImage key="image" src={SRC} alt={ALT} />, ' Data flow'] },
    { name: 'a link to a page that holds an image', href: '/architecture/data-flow', children: <LightboxImage src={SRC} alt={ALT} /> },
    { name: 'a link to an image file that holds an image without alt text', href: HREF, children: <LightboxImage src={SRC} alt="" /> },
  ])('follows $name', ({ href, children }) => {
    render(<LightboxLink href={href}>{children}</LightboxLink>);

    const link = screen.getByRole('link');

    expect(link).not.toHaveAttribute('aria-haspopup');
    expect(fireEvent.click(link)).toBe(true);
    expect(dialog()).toBeNull();
  });

  test('opens the image it holds in the lightbox', () => {
    imageLink();

    const link = screen.getByRole('link', { name: ALT });

    expect(link).toHaveAttribute('aria-haspopup', 'dialog');
    expect(link).toHaveAttribute('href', HREF);
    expect(link).toHaveClass('opener');
    expect(link.querySelector('img')).toHaveAttribute('src', SRC);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();

    expect(fireEvent.click(link)).toBe(false);

    expect(screen.getByRole('dialog', { name: ALT })).toBeInTheDocument();
    expect(dialog().querySelector('img')).toHaveAttribute('src', SRC);
    expect(screen.getByRole('link', { name: 'Open the image in a new tab' })).toHaveAttribute('href', HREF);

    fireEvent.click(screen.getByRole('button', { name: 'Close' }));

    expect(dialog()).toBeNull();
  });

  test.each([
    { name: 'stays open through the second half of a double-click on the link', opening: { detail: 1, clientX: 50, clientY: 60 }, expected: 1 },
    { name: 'closes on a click beside the image straight after the keyboard opens it', opening: { detail: 0 }, expected: 0 },
  ])('$name', ({ opening, expected }) => {
    jest.spyOn(performance, 'now').mockReturnValue(1000);
    imageLink();

    fireEvent.click(screen.getByRole('link', { name: ALT }), opening);
    fireEvent.click(zoomButton().parentElement, { detail: 1, clientX: 50, clientY: 60 });

    expect(document.querySelectorAll('dialog')).toHaveLength(expected);
  });

  test('opens an image with white space around it', () => {
    imageLink(['\n', <LightboxImage key="image" src={SRC} alt={ALT} />, ' ']);

    fireEvent.click(screen.getByRole('link', { name: ALT }));

    expect(screen.getByRole('dialog', { name: ALT })).toBeInTheDocument();
  });

  test.each([
    { name: 'Ctrl', init: { ctrlKey: true } },
    { name: 'Cmd', init: { metaKey: true } },
    { name: 'Shift', init: { shiftKey: true } },
    { name: 'Alt', init: { altKey: true } },
    { name: 'the middle button', init: { button: 1 } },
  ])('leaves a click with $name to the browser', ({ init }) => {
    imageLink();

    expect(fireEvent.click(screen.getByRole('link', { name: ALT }), init)).toBe(true);
    expect(dialog()).toBeNull();
  });
});
