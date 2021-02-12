import ApiClient from "./ApiClient";

export interface Category {
    id: number;
    name: string;
    slug: string;
}

export default class Categories extends ApiClient {
    protected url = "/categories";

    public async index({ trashed = false }): Promise<Category[]> {
        return super.index({ trashed });
    }
}
