/**
 * Route ability guard supports one ability (legacy) or an OR-list of
 * abilities. An empty list never grants access.
 */
export function isRouteAbilitySatisfied(required, { can, canAny } = {}) {
    if (required === null || required === undefined || required === '') return true;

    if (Array.isArray(required)) {
        return required.length > 0 && typeof canAny === 'function' && canAny(required);
    }

    return typeof can === 'function' && can(required);
}
