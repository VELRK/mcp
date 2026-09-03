import type { Dispatch } from "react";
import Slider from "rc-slider";
import "rc-slider/assets/index.css";
import type { FilterAction, FilterState } from "@/types/shopFilter";
import { setPriceRange } from "../../filterActions";
import { getCurrencySymbol } from "@/utils/formatPrice";

type Props = {
  cId: string;
  state: FilterState;
  dispatch: Dispatch<FilterAction>;
  priceMax: number;
};

export function PriceFacetSidebar({ cId, state, dispatch, priceMax }: Props) {
  const onPriceSliderChange = (value: number | number[]) => {
    if (!Array.isArray(value)) return;
    const [a, b] = value;
    const lo = Math.max(0, Math.min(a, b));
    const hi = Math.min(priceMax, Math.max(a, b));
    setPriceRange([lo, hi], dispatch);
  };

  return (
    <div className="widget-facet">
      <div
        className="facet-title"
        data-bs-target={`#${cId}-price`}
        role="button"
        data-bs-toggle="collapse"
        aria-expanded="true"
        aria-controls={`${cId}-price`}
      >
        <h6>Filter By Price</h6>
        <span className="icon icon-CaretDown" />
      </div>
      <div id={`${cId}-price`} className="collapse show">
        <div className="collapse-body widget-price filter-price">
          <div
            className="price-val-range"
            id="price-value-range"
            data-min={0}
            data-max={priceMax}
          >
            <Slider
              range
              min={0}
              max={priceMax}
              step={1}
              allowCross={false}
              value={[state.price[0], state.price[1]]}
              onChange={onPriceSliderChange}
              ariaLabelForHandle={["Minimum price", "Maximum price"]}
            />
          </div>
          <div className="price-box tf-grid-layout tf-col-2">
            <div className="box-wrap">
              <div className="price-val_wrap">
                <span className="cl-text-2 text-body-1" style={{ marginRight: '4px' }}>{getCurrencySymbol()}</span>
                <input
                  type="number"
                  className="price-val border-0 bg-transparent p-0 w-100"
                  id="price-min-value"
                  min={0}
                  max={priceMax}
                  value={state.price[0]}
                  onChange={(e) => {
                    const raw = Number(e.target.value) || 0;
                    const v = Math.max(0, Math.min(raw, priceMax));
                    const hi = state.price[1];
                    setPriceRange([Math.min(v, hi), Math.max(v, hi)], dispatch);
                  }}
                />
              </div>
            </div>
            <div className="box-wrap">
              <div className="price-val_wrap">
                <span className="cl-text-2 text-body-1" style={{ marginRight: '4px' }}>{getCurrencySymbol()}</span>
                <input
                  type="number"
                  className="price-val border-0 bg-transparent p-0 w-100"
                  id="price-max-value"
                  min={0}
                  max={priceMax}
                  value={state.price[1]}
                  onChange={(e) => {
                    const raw = Number(e.target.value) || 0;
                    const v = Math.max(0, Math.min(raw, priceMax));
                    const lo = state.price[0];
                    setPriceRange([Math.min(lo, v), Math.max(lo, v)], dispatch);
                  }}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
