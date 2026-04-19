<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/pag_access.php';

function pag_fetch_visible_menu_rows()
{
    if (!pag_schema_tables_ready()) {
        return [];
    }

    $sql = "
        SELECT
            p.id_pagina,
            p.titulo_menu,
            p.slug_pagina,
            p.id_padre,
            p.es_contenedor,
            p.visible_menu,
            p.id_permiso_requerido,
            p.icono,
            p.orden_menu,
            p.estado,
            pp.estado AS permiso_estado
        FROM pag_paginas p
        LEFT JOIN pag_permisos pp ON pp.id_permiso = p.id_permiso_requerido
        WHERE p.estado = 1
          AND p.visible_menu = 1
        ORDER BY
            CASE WHEN p.id_padre IS NULL THEN p.id_pagina ELSE p.id_padre END ASC,
            p.id_padre ASC,
            p.orden_menu ASC,
            p.id_pagina ASC
    ";

    $st = db()->query($sql);
    $rows = $st ? $st->fetchAll() : [];
    return is_array($rows) ? $rows : [];
}

function pag_sidebar_href_by_slug($slug)
{
    return 'pagina.php?slug=' . rawurlencode((string) $slug);
}

function pag_build_sidebar_menu_tree($currentSlug = '')
{
    $rows = pag_fetch_visible_menu_rows();
    if (!$rows) {
        return [];
    }

    $currentSlug = trim((string) $currentSlug);
    $roleIds = pag_get_user_role_ids_from_session();
    $allowedPermissionIds = pag_get_allowed_permission_ids_for_roles($roleIds);

    $rowsById = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id_pagina'] ?? 0);
        if ($id > 0) {
            $rowsById[$id] = $row;
        }
    }

    $childrenByParent = [];
    foreach ($rowsById as $id => $row) {
        $parentId = (int) ($row['id_padre'] ?? 0);
        if ($parentId > 0 && isset($rowsById[$parentId])) {
            $childrenByParent[$parentId][] = $row;
        }
    }

    $menu = [];
    foreach ($rowsById as $id => $row) {
        $parentId = (int) ($row['id_padre'] ?? 0);
        if ($parentId > 0) {
            continue;
        }

        $isContainer = ((int) ($row['es_contenedor'] ?? 0) === 1);
        $icon = trim((string) ($row['icono'] ?? ''));
        if ($icon === '') {
            $icon = $isContainer ? 'fas fa-folder' : 'fas fa-circle';
        }

        if (!$isContainer) {
            if (!pag_can_access_page_row($row, $roleIds, $allowedPermissionIds)) {
                continue;
            }
            $slug = (string) ($row['slug_pagina'] ?? '');
            $menu[] = [
                'type' => 'item',
                'title' => (string) ($row['titulo_menu'] ?? ''),
                'icon' => $icon,
                'slug' => $slug,
                'active' => ($slug !== '' && $slug === $currentSlug),
            ];
            continue;
        }

        $children = [];
        $childrenRows = $childrenByParent[$id] ?? [];
        foreach ($childrenRows as $child) {
            if ((int) ($child['es_contenedor'] ?? 0) === 1) {
                continue;
            }
            if (!pag_can_access_page_row($child, $roleIds, $allowedPermissionIds)) {
                continue;
            }

            $childSlug = (string) ($child['slug_pagina'] ?? '');
            $children[] = [
                'title' => (string) ($child['titulo_menu'] ?? ''),
                'slug' => $childSlug,
                'icon' => trim((string) ($child['icono'] ?? '')),
                'active' => ($childSlug !== '' && $childSlug === $currentSlug),
            ];
        }

        if (!$children) {
            continue;
        }

        $menuOpen = false;
        foreach ($children as $child) {
            if (!empty($child['active'])) {
                $menuOpen = true;
                break;
            }
        }

        $menu[] = [
            'type' => 'group',
            'title' => (string) ($row['titulo_menu'] ?? ''),
            'icon' => $icon,
            'slug' => (string) ($row['slug_pagina'] ?? ''),
            'open' => $menuOpen,
            'children' => $children,
        ];
    }

    return $menu;
}

function pag_render_sidebar_menu($currentSlug = '')
{
    $tree = pag_build_sidebar_menu_tree($currentSlug);
    if (!$tree) {
        return '';
    }

    $out = '';
    foreach ($tree as $node) {
        if (($node['type'] ?? '') === 'item') {
            $title = htmlspecialchars((string) ($node['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $icon = htmlspecialchars((string) ($node['icon'] ?? 'fas fa-circle'), ENT_QUOTES, 'UTF-8');
            $slug = (string) ($node['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $href = htmlspecialchars(pag_sidebar_href_by_slug($slug), ENT_QUOTES, 'UTF-8');
            $activeClass = !empty($node['active']) ? ' active' : '';
            $out .= '<li class="nav-item">';
            $out .= '<a href="' . $href . '" class="nav-link' . $activeClass . '">';
            $out .= '<i class="nav-icon ' . $icon . '"></i><p>' . $title . '</p>';
            $out .= '</a></li>';
            continue;
        }

        if (($node['type'] ?? '') !== 'group') {
            continue;
        }

        $title = htmlspecialchars((string) ($node['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars((string) ($node['icon'] ?? 'fas fa-folder'), ENT_QUOTES, 'UTF-8');
        $openClass = !empty($node['open']) ? ' menu-open' : '';
        $activeParentClass = !empty($node['open']) ? ' active' : '';

        $out .= '<li class="nav-item has-treeview' . $openClass . '">';
        $out .= '<a href="#" class="nav-link' . $activeParentClass . '">';
        $out .= '<i class="nav-icon ' . $icon . '"></i><p>' . $title . '<i class="right fas fa-angle-left"></i></p>';
        $out .= '</a>';
        $out .= '<ul class="nav nav-treeview">';

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($children as $child) {
            $childSlug = (string) ($child['slug'] ?? '');
            if ($childSlug === '') {
                continue;
            }
            $childTitle = htmlspecialchars((string) ($child['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $childHref = htmlspecialchars(pag_sidebar_href_by_slug($childSlug), ENT_QUOTES, 'UTF-8');
            $childActiveClass = !empty($child['active']) ? ' active' : '';
            $out .= '<li class="nav-item">';
            $out .= '<a href="' . $childHref . '" class="nav-link' . $childActiveClass . '">';
            $out .= '<i class="far fa-circle nav-icon"></i><p>' . $childTitle . '</p>';
            $out .= '</a></li>';
        }

        $out .= '</ul></li>';
    }

    return $out;
}
