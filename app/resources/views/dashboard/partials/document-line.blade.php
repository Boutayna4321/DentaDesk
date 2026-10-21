<tr data-line>
    <td class="ps-3"><input class="form-control form-control-sm" name="lines[{{ $i }}][label]" value="{{ $l['label'] }}" required placeholder="Ex : Composite 2 faces"></td>
    <td><input class="form-control form-control-sm mono" name="lines[{{ $i }}][tooth]" value="{{ $l['tooth'] }}" inputmode="numeric" placeholder="—"></td>
    <td><input class="form-control form-control-sm mono" name="lines[{{ $i }}][code]" value="{{ $l['code'] }}" placeholder="D 30"></td>
    <td><input class="form-control form-control-sm" name="lines[{{ $i }}][quantity]" value="{{ $l['quantity'] }}" type="number" min="1" max="99" data-qty></td>
    <td><input class="form-control form-control-sm text-end" name="lines[{{ $i }}][unit_price]" value="{{ $l['unit_price'] }}" inputmode="decimal" required data-price></td>
    <td class="text-end fw-bold" data-amount></td>
    <td><button type="button" class="btn btn-link text-danger p-0" data-remove-line aria-label="Retirer la ligne">✕</button></td>
</tr>
