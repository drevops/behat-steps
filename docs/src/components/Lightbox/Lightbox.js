import { Children, createContext, isValidElement, useContext, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import clsx from 'clsx';
import MDXA from '@theme/MDXComponents/A';
import MDXImg from '@theme/MDXComponents/Img';
import Icon from '@site/src/components/Icon';
import styles from './Lightbox.module.css';

const IMAGE_FILE = /\.(avif|gif|jpe?g|png|svg|webp)$/i;

// A press that moves fewer pixels than this before its release is a click.
const DRAG_THRESHOLD = 4;

// A button around an image inside a link would nest 1 control in another.
const InsideLink = createContext(false);

// Whether a link points at an image file, ignoring its query and fragment.
export const isImageFile = (href) => typeof href === 'string' && IMAGE_FILE.test(href.split(/[?#]/)[0]);

// A zoomed image shows at its natural size, and at least twice its fitted
// size, so a zoom always enlarges it.
export const zoomedWidth = (naturalWidth, fittedWidth) => Math.max(naturalWidth, fittedWidth * 2);

// The scroll offsets that bring the point at the given fractions of the
// image's width and height back under the given client position.
export const scrollToKeep = ({ point, fraction }, rect, view) => ({
  left: view.scrollLeft + rect.left + fraction.x * rect.width - point.x,
  top: view.scrollTop + rect.top + fraction.y * rect.height - point.y,
});

const fractionOf = (position, start, length) => Math.min(Math.max((position - start) / length, 0), 1);

// Shows 1 image in a modal dialog for as long as it's mounted. Selecting the
// image switches between fitting it in the window and zooming it, and a mouse
// drag pans a zoomed image.
const Lightbox = ({ src, alt, href, onClose }) => {
  const dialog = useRef(null);
  const view = useRef(null);
  const zoom = useRef(null);
  const image = useRef(null);
  const anchor = useRef(null);
  const drag = useRef(null);
  const dragged = useRef(false);
  const [width, setWidth] = useState(null);
  const [dragging, setDragging] = useState(false);
  const zoomed = width !== null;

  useEffect(() => {
    // An effect that runs twice finds the dialog already open.
    if (!dialog.current.open) {
      dialog.current.showModal();
    }

    zoom.current.focus();
  }, []);

  useLayoutEffect(() => {
    if (anchor.current === null) {
      return;
    }

    const { left, top } = scrollToKeep(anchor.current, image.current.getBoundingClientRect(), view.current);

    view.current.scrollLeft = left;
    view.current.scrollTop = top;
    anchor.current = null;
  }, [width]);

  const close = () => dialog.current.close();

  const toggleZoom = (event) => {
    if (dragged.current) {
      return;
    }

    if (zoomed) {
      setWidth(null);

      return;
    }

    const rect = image.current.getBoundingClientRect();

    // An image that hasn't loaded has no size to zoom from.
    if (rect.width === 0) {
      return;
    }

    const bounds = view.current.getBoundingClientRect();
    // A click from the keyboard has no pointer position, so the zoom keeps the
    // middle of the view in place.
    const point = event.detail === 0 ? { x: bounds.left + bounds.width / 2, y: bounds.top + bounds.height / 2 } : { x: event.clientX, y: event.clientY };

    anchor.current = { point, fraction: { x: fractionOf(point.x, rect.left, rect.width), y: fractionOf(point.y, rect.top, rect.height) } };
    setWidth(zoomedWidth(image.current.naturalWidth, rect.width));
  };

  const startDrag = (event) => {
    dragged.current = false;

    if (!zoomed || event.pointerType !== 'mouse' || event.button !== 0) {
      return;
    }

    drag.current = { x: event.clientX, y: event.clientY, left: view.current.scrollLeft, top: view.current.scrollTop };
  };

  const endDrag = () => {
    drag.current = null;
    setDragging(false);
  };

  const cancelDrag = () => {
    endDrag();
    dragged.current = false;
  };

  const moveDrag = (event) => {
    if (drag.current === null) {
      return;
    }

    // A press released outside the view never reaches its pointerup handler.
    if ((event.buttons & 1) === 0) {
      endDrag();

      return;
    }

    const dx = event.clientX - drag.current.x;
    const dy = event.clientY - drag.current.y;

    if (!dragged.current) {
      if (Math.hypot(dx, dy) < DRAG_THRESHOLD) {
        return;
      }

      // A captured pointer clicks the view rather than the image, so the
      // capture waits until the press turns into a drag.
      view.current.setPointerCapture(event.pointerId);
      dragged.current = true;
      setDragging(true);
    }

    view.current.scrollLeft = drag.current.left - dx;
    view.current.scrollTop = drag.current.top - dy;
  };

  // The click that ends a drag does nothing, and a click beside the image
  // closes the dialog.
  const clickView = (event) => {
    if (dragged.current) {
      dragged.current = false;

      return;
    }

    if (event.target === event.currentTarget) {
      close();
    }
  };

  return createPortal(
    <dialog ref={dialog} className={styles.lightbox} aria-label={alt} onClose={onClose}>
      <div
        ref={view}
        className={clsx(styles.view, dragging && styles.dragging)}
        onClick={clickView}
        onPointerDown={startDrag}
        onPointerMove={moveDrag}
        onPointerUp={endDrag}
        onPointerCancel={cancelDrag}
      >
        <button ref={zoom} type="button" className={clsx(styles.zoom, zoomed && styles.zoomed)} aria-label={zoomed ? 'Zoom out' : 'Zoom in'} onClick={toggleZoom}>
          <img ref={image} src={src} alt={alt} draggable={false} style={zoomed ? { width: `${width}px` } : undefined} />
        </button>
      </div>
      <div className={styles.toolbar}>
        {href && (
          <a className={styles.action} href={href} target="_blank" rel="noopener noreferrer" title="Open the image in a new tab">
            <Icon name="arrowUpRight" className={styles.icon} />
            <span className="visually-hidden">Open the image in a new tab</span>
          </a>
        )}
        <button type="button" className={styles.action} title="Close" onClick={close}>
          <Icon name="close" className={styles.icon} />
          <span className="visually-hidden">Close</span>
        </button>
      </div>
    </dialog>,
    document.body,
  );
};

// Wraps an image with alt text in a button that opens it in the lightbox. An
// image inside a link stays as it is, and so does an image without alt text,
// since a button needs a name.
export const LightboxImage = (props) => {
  const insideLink = useContext(InsideLink);
  const [open, setOpen] = useState(false);

  if (insideLink || !props.alt) {
    return <MDXImg {...props} />;
  }

  return (
    <>
      <button type="button" className={clsx(styles.opener, styles.imageButton)} aria-haspopup="dialog" onClick={() => setOpen(true)}>
        <MDXImg {...props} />
      </button>
      {open && <Lightbox src={props.src} alt={props.alt} onClose={() => setOpen(false)} />}
    </>
  );
};

// The props of the image a link holds, when the link holds nothing else but
// white space.
const imageOf = (children) => {
  const content = Children.toArray(children).filter((child) => typeof child !== 'string' || child.trim() !== '');

  return content.length === 1 && isValidElement(content[0]) && content[0].type === LightboxImage ? content[0].props : null;
};

// Opens the image a link holds in the lightbox rather than following the
// link. A click with a modifier key or another mouse button keeps the
// browser's behavior, such as opening the file in a new tab.
const ImageLink = ({ image, ...props }) => {
  const [open, setOpen] = useState(false);

  const show = (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }

    event.preventDefault();
    setOpen(true);
  };

  return (
    <>
      <MDXA {...props} className={clsx(props.className, styles.opener)} aria-haspopup="dialog" onClick={show} />
      {open && <Lightbox src={image.src} alt={image.alt} href={props.href} onClose={() => setOpen(false)} />}
    </>
  );
};

// A link to an image file that holds only an image with alt text opens that
// image in the lightbox. Every link tells the images inside it that a link
// holds them.
export const LightboxLink = (props) => {
  const image = isImageFile(props.href) ? imageOf(props.children) : null;

  return <InsideLink.Provider value>{image?.alt ? <ImageLink {...props} image={image} /> : <MDXA {...props} />}</InsideLink.Provider>;
};

export default Lightbox;
