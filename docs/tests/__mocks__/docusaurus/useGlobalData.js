// Stands in for the Docusaurus hook, which needs the build context that a unit
// test does not have. A test sets the data a plugin published with
// 'usePluginData.mockReturnValue()'.
export const usePluginData = jest.fn();
