/**
 * fd-kpi-manager / admin.js
 * 繰り返しフィールド（グループ・取り組み・KPI）の追加・削除・インデックス再採番
 */
(function ($) {
    'use strict';

    /* ===== インデックスカウンタ ===== */
    var groupCounter = 0;

    /* ===== 初期化 ===== */
    $(function () {
        // 既存グループのカウンタ最大値を把握
        $('#fd-kpi-groups-container .fd-kpi-group').each(function () {
            var gi = parseInt($(this).data('group'), 10);
            if (!isNaN(gi) && gi >= groupCounter) {
                groupCounter = gi + 1;
            }
        });

        // 既存グループの番号表示を更新
        reNumberGroups();

        /* ---- グループ追加 ---- */
        $('#fd-kpi-add-group').on('click', function () {
            var tpl = $('#fd-kpi-group-tpl').html();
            tpl = tpl.replace(/__GI__/g, groupCounter);
            var $group = $(tpl);
            $group.attr('data-group', groupCounter);
            $('#fd-kpi-groups-container').append($group);
            reNumberGroups();
            groupCounter++;
        });

        /* ---- グループ削除（委任） ---- */
        $('#fd-kpi-groups-container').on('click', '.fd-kpi-remove-group', function () {
            if (!confirm('このグループを削除しますか？')) return;
            $(this).closest('.fd-kpi-group').remove();
            reNumberGroups();
        });

        /* ---- 取り組み追加（委任） ---- */
        $('#fd-kpi-groups-container').on('click', '.fd-kpi-add-approach', function () {
            var $group  = $(this).closest('.fd-kpi-group');
            var gi      = $group.data('group');
            var $cont   = $group.find('.fd-approaches-container');
            var ai      = $cont.find('.fd-approach-row').length;

            var tpl = $('#fd-kpi-approach-tpl').html();
            tpl = tpl.replace(/__GI__/g, gi).replace(/__AI__/g, ai);
            $cont.append($(tpl));
        });

        /* ---- 取り組み削除（委任） ---- */
        $('#fd-kpi-groups-container').on('click', '.fd-kpi-remove-approach', function () {
            $(this).closest('.fd-approach-row').remove();
        });

        /* ---- KPI追加（委任） ---- */
        $('#fd-kpi-groups-container').on('click', '.fd-kpi-add-kpi', function () {
            var $group = $(this).closest('.fd-kpi-group');
            var gi     = $group.data('group');
            var $cont  = $group.find('.fd-kpis-container');
            var ki     = $cont.find('.fd-kpi-row').length;

            var tpl = $('#fd-kpi-kpi-tpl').html();
            tpl = tpl.replace(/__GI__/g, gi).replace(/__KI__/g, ki);
            $cont.append($(tpl));
        });

        /* ---- KPI削除（委任） ---- */
        $('#fd-kpi-groups-container').on('click', '.fd-kpi-remove-kpi', function () {
            if (!confirm('このKPIを削除しますか？')) return;
            $(this).closest('.fd-kpi-row').remove();
        });
    });

    /* ===== グループ番号の表示更新 ===== */
    function reNumberGroups() {
        $('#fd-kpi-groups-container .fd-kpi-group').each(function (idx) {
            $(this).find('.fd-group-num').first().text(idx + 1);
        });
    }

}(jQuery));
