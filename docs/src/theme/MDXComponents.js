import MDXComponents from '@theme-original/MDXComponents';
import { LightboxImage, LightboxLink } from '@site/src/components/Lightbox';
import ScrollableTable from '@site/src/components/ScrollableTable';

export default {
  ...MDXComponents,
  a: LightboxLink,
  img: LightboxImage,
  table: ScrollableTable,
};
